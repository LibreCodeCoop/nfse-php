<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Http;

use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Config\EnvironmentConfig;
use LibreCodeCoop\NfsePHP\Contracts\DpsLookupInterface;
use LibreCodeCoop\NfsePHP\Contracts\EventLookupInterface;
use LibreCodeCoop\NfsePHP\Contracts\HttpTransportInterface;
use LibreCodeCoop\NfsePHP\Contracts\NfseClientInterface;
use LibreCodeCoop\NfsePHP\Contracts\SecretStoreInterface;
use LibreCodeCoop\NfsePHP\Contracts\XmlSignerInterface;
use LibreCodeCoop\NfsePHP\Danfse\DanfseGenerator;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\EventReceiptData;
use LibreCodeCoop\NfsePHP\Dto\HttpRequestData;
use LibreCodeCoop\NfsePHP\Dto\ReceiptData;
use LibreCodeCoop\NfsePHP\Exception\CancellationException;
use LibreCodeCoop\NfsePHP\Exception\IssuanceException;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;
use LibreCodeCoop\NfsePHP\Exception\NfseErrorCode;
use LibreCodeCoop\NfsePHP\Exception\QueryException;
use LibreCodeCoop\NfsePHP\Support\DpsIdentifier;
use LibreCodeCoop\NfsePHP\Support\GzipBase64;
use LibreCodeCoop\NfsePHP\Xml\DpsSigner;
use LibreCodeCoop\NfsePHP\Xml\XmlBuilder;

/**
 * HTTP client for the SEFIN Nacional NFS-e REST API.
 *
 * Communicates with the SEFIN gateway to issue, query, and cancel NFS-e.
 * All requests carry a signed DPS XML payload.
 */
class NfseClient implements NfseClientInterface, DpsLookupInterface, EventLookupInterface
{
    private readonly string $baseUrl;
    private readonly XmlSignerInterface $signer;
    private readonly DanfseGenerator $danfseGenerator;
    private readonly HttpTransportInterface $transport;

    public function __construct(
        private readonly EnvironmentConfig $environment,
        private readonly CertConfig $cert,
        private readonly SecretStoreInterface $secretStore,
        ?XmlSignerInterface $signer = null,
        ?DanfseGenerator $danfseGenerator = null,
        ?HttpTransportInterface $transport = null,
    ) {
        $this->baseUrl         = $environment->baseUrl;
        $this->signer          = $signer ?? new DpsSigner($secretStore);
        $this->danfseGenerator = $danfseGenerator ?? new DanfseGenerator();
        $this->transport       = $transport ?? new RetryingHttpTransport(new NativeStreamTransport());
    }

    public function emit(DpsData $dps): ReceiptData
    {
        $xml    = (new XmlBuilder())->buildDps($dps);
        $signed = $this->signer->sign($xml, $dps->cnpjPrestador);

        [$httpStatus, $body] = $this->post('/nfse', $signed);

        if ($httpStatus >= 400) {
            throw new IssuanceException(
                'SEFIN gateway rejected issuance (HTTP ' . $httpStatus . ')',
                NfseErrorCode::IssuanceRejected,
                $httpStatus,
                $body,
            );
        }

        return $this->parseReceiptResponse($body);
    }

    public function query(string $chaveAcesso): ReceiptData
    {
        [$httpStatus, $body] = $this->get('/nfse/' . $chaveAcesso);

        if ($httpStatus >= 400) {
            throw new QueryException(
                'SEFIN gateway returned error for query (HTTP ' . $httpStatus . ')',
                NfseErrorCode::QueryFailed,
                $httpStatus,
                $body,
            );
        }

        return $this->parseReceiptResponse($body);
    }

    #[\Override]
    public function queryDps(string $idDps): string
    {
        $id = rawurlencode(DpsIdentifier::forApi($idDps));
        [$httpStatus, $body] = $this->get('/dps/' . $id);

        if ($httpStatus >= 400) {
            throw new QueryException(
                'SEFIN gateway returned error for DPS query (HTTP ' . $httpStatus . ')',
                NfseErrorCode::QueryFailed,
                $httpStatus,
                $body,
            );
        }

        $chaveAcesso = isset($body['chaveAcesso']) && is_scalar($body['chaveAcesso'])
            ? trim((string) $body['chaveAcesso'])
            : '';

        if ($chaveAcesso === '') {
            throw new NetworkException(
                'SEFIN gateway returned a DPS query response without chaveAcesso.',
                NfseErrorCode::InvalidResponse,
            );
        }

        return $chaveAcesso;
    }

    #[\Override]
    public function existsDps(string $idDps): bool
    {
        $id = rawurlencode(DpsIdentifier::forApi($idDps));
        $httpStatus = $this->head('/dps/' . $id);

        if ($httpStatus === 404) {
            return false;
        }

        if ($httpStatus >= 400 || $httpStatus === 0) {
            throw new QueryException(
                'SEFIN gateway returned error for DPS existence check (HTTP ' . $httpStatus . ')',
                NfseErrorCode::QueryFailed,
                $httpStatus,
            );
        }

        return true;
    }

    #[\Override]
    public function queryEvent(string $chaveAcesso, int $tipoEvento, int $numSeqEvento = 1): EventReceiptData
    {
        if ($tipoEvento < 100000 || $tipoEvento > 999999) {
            throw new \InvalidArgumentException('Event type must be a six-digit code.');
        }

        if ($numSeqEvento < 1) {
            throw new \InvalidArgumentException('Event sequence number must be at least 1.');
        }

        [$httpStatus, $body] = $this->get(
            '/nfse/' . rawurlencode($chaveAcesso)
            . '/eventos/' . $tipoEvento
            . '/' . $numSeqEvento,
        );

        if ($httpStatus >= 400) {
            throw new QueryException(
                'SEFIN gateway returned error for event query (HTTP ' . $httpStatus . ')',
                NfseErrorCode::QueryFailed,
                $httpStatus,
                $body,
            );
        }

        return $this->parseEventResponse($body);
    }

    public function cancel(string $chaveAcesso, string $motivo): bool
    {
        $eventoXml       = $this->buildCancelEventXml($chaveAcesso, $motivo);
        $signedEventoXml = $this->signer->sign($eventoXml, $this->cert->cnpj);

        $compressedEventoXml = gzencode($signedEventoXml);

        if ($compressedEventoXml === false) {
            throw new NetworkException('Failed to compress cancellation event XML payload before transmission.');
        }

        [$httpStatus, $body] = $this->postEvento(
            '/nfse/' . $chaveAcesso . '/eventos',
            base64_encode($compressedEventoXml),
        );

        if ($httpStatus >= 400) {
            throw new CancellationException(
                'SEFIN gateway rejected cancellation (HTTP ' . $httpStatus . ')',
                NfseErrorCode::CancellationRejected,
                $httpStatus,
                $body,
            );
        }

        return true;
    }

    #[\Override]
    public function getDanfse(string $nfseXml): string
    {
        return $this->danfseGenerator->generateFromXml($nfseXml);
    }

    // -------------------------------------------------------------------------
    // Internal HTTP helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{int, array<string, mixed>}
     */
    private function post(string $path, string $xmlPayload): array
    {
        $compressedPayload = gzencode($xmlPayload);

        if ($compressedPayload === false) {
            throw new NetworkException('Failed to compress DPS XML payload before transmission.');
        }

        $payload = json_encode([
            'dpsXmlGZipB64' => base64_encode($compressedPayload),
        ], JSON_THROW_ON_ERROR);

        return $this->fetchAndDecode($path, 'POST', $payload);
    }

    /**
     * @return array{int, array<string, mixed>}
     */
    private function get(string $path): array
    {
        return $this->fetchAndDecode($path, 'GET');
    }

    private function head(string $path): int
    {
        return $this->request($path, 'HEAD')->status;
    }

    /**
     * @return array{int, array<string, mixed>}
     */
    private function postEvento(string $path, string $eventoXmlGZipB64): array
    {
        $payload = json_encode([
            'pedidoRegistroEventoXmlGZipB64' => $eventoXmlGZipB64,
        ], JSON_THROW_ON_ERROR);

        return $this->fetchAndDecode($path, 'POST', $payload);
    }

    private function request(string $path, string $method, ?string $jsonPayload = null): \LibreCodeCoop\NfsePHP\Dto\HttpResponseData
    {
        $headers = ['Accept' => 'application/json'];

        if ($jsonPayload !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        return $this->transport->request(new HttpRequestData(
            method: $method,
            url: $this->baseUrl . $path,
            headers: $headers,
            body: $jsonPayload,
            timeoutSeconds: $this->environment->requestTimeoutSeconds,
            clientCertificatePath: $this->cert->transportCertificatePath,
            clientPrivateKeyPath: $this->cert->transportPrivateKeyPath,
        ));
    }

    /**
     * @return array{int, array<string, mixed>}
     */
    private function fetchAndDecode(string $path, string $method, ?string $jsonPayload = null): array
    {
        $response = $this->request($path, $method, $jsonPayload);
        $body = $response->body;

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $responsePreview = trim(substr(strip_tags($body), 0, 180));

            throw new NetworkException(
                'Unexpected non-JSON response from SEFIN gateway' . ($responsePreview !== '' ? ': ' . $responsePreview : ''),
                NfseErrorCode::InvalidResponse,
                $e,
            );
        }

        if (!is_array($decoded)) {
            throw new NetworkException(
                'Unexpected response format from SEFIN gateway',
                NfseErrorCode::InvalidResponse,
            );
        }

        return [$response->status, $decoded];
    }

    private function buildCancelEventXml(string $chaveAcesso, string $motivo): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'pedRegEvento');
        $root->setAttribute('versao', '1.01');
        $doc->appendChild($root);

        $infPedReg = $doc->createElement('infPedReg');
        $infPedReg->setAttribute('Id', 'PRE' . $chaveAcesso . '101101');
        $root->appendChild($infPedReg);

        $infPedReg->appendChild($doc->createElement('tpAmb', $this->environment->sandboxMode ? '2' : '1'));
        $infPedReg->appendChild($doc->createElement('verAplic', 'akaunting-nfse'));
        $infPedReg->appendChild($doc->createElement('dhEvento', (new \DateTimeImmutable())->format('Y-m-d\\TH:i:sP')));
        $infPedReg->appendChild($doc->createElement('CNPJAutor', $this->cert->cnpj));
        $infPedReg->appendChild($doc->createElement('chNFSe', $chaveAcesso));

        $e101101 = $doc->createElement('e101101');
        $e101101->appendChild($doc->createElement('xDesc', 'Cancelamento de NFS-e'));
        $e101101->appendChild($doc->createElement('cMotivo', '1'));
        $e101101->appendChild($doc->createElement('xMotivo', $motivo));
        $infPedReg->appendChild($e101101);

        return $doc->saveXML($doc->documentElement) ?: '';
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseEventResponse(array $response): EventReceiptData
    {
        $encodedXml = $response['eventoXmlGZipB64'] ?? null;

        if (!is_string($encodedXml) || $encodedXml === '') {
            throw new NetworkException(
                'SEFIN gateway returned an event response without eventoXmlGZipB64.',
                NfseErrorCode::InvalidResponse,
            );
        }

        $rawXml = GzipBase64::decode($encodedXml, 'SEFIN event XML response');

        if (trim($rawXml) === '') {
            throw new NetworkException(
                'SEFIN gateway returned an empty event XML.',
                NfseErrorCode::InvalidResponse,
            );
        }

        return new EventReceiptData(
            tipoAmbiente: (int) ($response['tipoAmbiente'] ?? 0),
            versaoAplicativo: (string) ($response['versaoAplicativo'] ?? ''),
            dataHoraProcessamento: (string) ($response['dataHoraProcessamento'] ?? ''),
            rawXml: $rawXml,
        );
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseReceiptResponse(array $response): ReceiptData
    {
        $rawXml = null;

        if (isset($response['nfseXmlGZipB64']) && is_string($response['nfseXmlGZipB64'])) {
            $rawXml = GzipBase64::decode($response['nfseXmlGZipB64'], 'SEFIN NFS-e XML response');
        }

        return new ReceiptData(
            nfseNumber:        (string) ($response['nNFSe'] ?? $response['numero'] ?? ''),
            chaveAcesso:       (string) ($response['chaveAcesso'] ?? ''),
            dataEmissao:       (string) ($response['dhEmi'] ?? $response['dataHoraProcessamento'] ?? $response['dataEmissao'] ?? ''),
            codigoVerificacao: isset($response['codigoVerificacao']) ? (string) $response['codigoVerificacao'] : null,
            rawXml:            $rawXml,
        );
    }
}
