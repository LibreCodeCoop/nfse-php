<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Http;

use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Config\EnvironmentConfig;
use LibreCodeCoop\NfsePHP\Contracts\CancellationClientInterface;
use LibreCodeCoop\NfsePHP\Contracts\DecisionNfseIssuerInterface;
use LibreCodeCoop\NfsePHP\Contracts\DpsLookupInterface;
use LibreCodeCoop\NfsePHP\Contracts\EventLookupInterface;
use LibreCodeCoop\NfsePHP\Contracts\EventRegistrationInterface;
use LibreCodeCoop\NfsePHP\Contracts\HttpTransportInterface;
use LibreCodeCoop\NfsePHP\Contracts\NfseClientInterface;
use LibreCodeCoop\NfsePHP\Contracts\SecretStoreInterface;
use LibreCodeCoop\NfsePHP\Contracts\XmlSignerInterface;
use LibreCodeCoop\NfsePHP\Danfse\DanfseGenerator;
use LibreCodeCoop\NfsePHP\Dto\DecisionNfseData;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\EventReceiptData;
use LibreCodeCoop\NfsePHP\Dto\EventRegistrationData;
use LibreCodeCoop\NfsePHP\Dto\HttpRequestData;
use LibreCodeCoop\NfsePHP\Dto\ReceiptData;
use LibreCodeCoop\NfsePHP\Exception\CancellationException;
use LibreCodeCoop\NfsePHP\Exception\EventRegistrationException;
use LibreCodeCoop\NfsePHP\Exception\IssuanceException;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;
use LibreCodeCoop\NfsePHP\Exception\NfseErrorCode;
use LibreCodeCoop\NfsePHP\Exception\QueryException;
use LibreCodeCoop\NfsePHP\Support\DpsIdentifier;
use LibreCodeCoop\NfsePHP\Support\GzipBase64;
use LibreCodeCoop\NfsePHP\Xml\DecisionNfseBuilder;
use LibreCodeCoop\NfsePHP\Xml\DpsSigner;
use LibreCodeCoop\NfsePHP\Xml\XmlBuilder;

/**
 * HTTP client for the SEFIN Nacional NFS-e REST API.
 *
 * Communicates with the SEFIN gateway to issue, query, and cancel NFS-e.
 * All requests carry a signed DPS XML payload.
 */
class NfseClient implements NfseClientInterface, CancellationClientInterface, DecisionNfseIssuerInterface, DpsLookupInterface, EventLookupInterface, EventRegistrationInterface
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

    #[\Override]
    public function emitDecision(DecisionNfseData $nfse): ReceiptData
    {
        $xml = (new DecisionNfseBuilder())->build($nfse);
        $signed = $this->signer->sign($xml, $nfse->dps->cnpjPrestador);

        [$httpStatus, $body] = $this->postCompressedXml(
            '/decisao-judicial/nfse',
            'xmlGZipB64',
            $signed,
        );

        if ($httpStatus >= 400) {
            throw new IssuanceException(
                'SEFIN gateway rejected decision-flow issuance (HTTP ' . $httpStatus . ')',
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

    #[\Override]
    public function registerEventXml(string $chaveAcesso, string $eventXml): EventRegistrationData
    {
        $chaveAcesso = trim($chaveAcesso);
        if ($chaveAcesso === '') {
            throw new \InvalidArgumentException('NFS-e access key cannot be empty.');
        }

        $this->assertEventXmlMatchesAccessKey($eventXml, $chaveAcesso);
        $signedEventXml = $this->signer->sign($eventXml, $this->cert->cnpj);

        $compressedEventXml = gzencode($signedEventXml);
        if ($compressedEventXml === false) {
            throw new NetworkException('Failed to compress event XML payload before transmission.');
        }

        [$httpStatus, $body] = $this->postEvento(
            '/nfse/' . rawurlencode($chaveAcesso) . '/eventos',
            base64_encode($compressedEventXml),
        );

        if ($httpStatus >= 400) {
            throw new EventRegistrationException(
                'SEFIN gateway rejected event registration (HTTP ' . $httpStatus . ')',
                NfseErrorCode::EventRegistrationRejected,
                $httpStatus,
                $body,
            );
        }

        return new EventRegistrationData(
            accepted: (bool) ($body['sucesso'] ?? true),
            httpStatus: $httpStatus,
            response: $body,
        );
    }

    public function cancel(string $chaveAcesso, string $motivo): bool
    {
        return $this->cancelWithReason($chaveAcesso, '1', $motivo);
    }

    #[\Override]
    public function cancelWithReason(string $chaveAcesso, string $codigoMotivo, string $motivo): bool
    {
        $codigoMotivo = trim($codigoMotivo);
        $motivo = trim($motivo);
        $this->assertCancellationReason($codigoMotivo, $motivo);

        $eventoXml = $this->buildCancelEventXml($chaveAcesso, $codigoMotivo, $motivo);

        try {
            $this->registerEventXml($chaveAcesso, $eventoXml);
        } catch (EventRegistrationException $e) {
            throw new CancellationException(
                'SEFIN gateway rejected cancellation (HTTP ' . $e->httpStatus . ')',
                NfseErrorCode::CancellationRejected,
                $e->httpStatus,
                $e->upstreamPayload,
                $e,
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
        return $this->postCompressedXml($path, 'dpsXmlGZipB64', $xmlPayload);
    }

    /**
     * @return array{int, array<string, mixed>}
     */
    private function postCompressedXml(string $path, string $field, string $xmlPayload): array
    {
        $compressedPayload = gzencode($xmlPayload);

        if ($compressedPayload === false) {
            throw new NetworkException('Failed to compress fiscal XML payload before transmission.');
        }

        $payload = json_encode([
            $field => base64_encode($compressedPayload),
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

    private function assertEventXmlMatchesAccessKey(string $eventXml, string $chaveAcesso): void
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->loadXML($eventXml, LIBXML_NONET)) {
                throw new \InvalidArgumentException('Event XML must be well formed.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'pedRegEvento') {
            throw new \InvalidArgumentException('Event XML root element must be pedRegEvento.');
        }

        $xpath = new \DOMXPath($document);
        $accessKeyNode = $xpath->query('//*[local-name()="chNFSe"]')->item(0);
        if (!$accessKeyNode instanceof \DOMElement || trim($accessKeyNode->textContent) !== $chaveAcesso) {
            throw new \InvalidArgumentException('Event XML access key does not match the target NFS-e.');
        }

        $eventNodes = $xpath->query('//*[starts-with(local-name(), "e")]');
        $hasTypedEvent = false;

        if ($eventNodes !== false) {
            foreach ($eventNodes as $eventNode) {
                if ($eventNode instanceof \DOMElement && preg_match('/^e\d{6}$/', $eventNode->localName) === 1) {
                    $hasTypedEvent = true;
                    break;
                }
            }
        }

        if (!$hasTypedEvent) {
            throw new \InvalidArgumentException('Event XML must contain a six-digit typed event element.');
        }
    }

    private function buildCancelEventXml(string $chaveAcesso, string $codigoMotivo, string $motivo): string
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
        $e101101->appendChild($doc->createElement('cMotivo', $codigoMotivo));
        $e101101->appendChild($doc->createElement('xMotivo', $motivo));
        $infPedReg->appendChild($e101101);

        return $doc->saveXML($doc->documentElement) ?: '';
    }

    private function assertCancellationReason(string $codigoMotivo, string $motivo): void
    {
        if (!in_array($codigoMotivo, ['1', '2', '9'], true)) {
            throw new \InvalidArgumentException('Cancellation reason code must be one of: 1, 2, 9.');
        }

        $length = preg_match_all('/./us', $motivo, $characters);
        if ($length === false) {
            throw new \InvalidArgumentException('Cancellation reason description must be valid UTF-8.');
        }

        if ($length < 15 || $length > 255) {
            throw new \InvalidArgumentException('Cancellation reason description must contain between 15 and 255 characters.');
        }
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
     * The documented SEFIN 201 response provides chaveAcesso and
     * nfseXmlGZipB64, not a top-level nNFSe. Read the number from the
     * authorized NFSe/infNFSe/nNFSe rather than treating a blank number as
     * successful issuance.
     *
     * An incomplete 2xx receipt is an ambiguous result, not a fiscal
     * rejection or authorization. Callers must recover by DPS before any
     * further POST, just like a transport timeout.
     *
     * @param array<string, mixed> $response
     */
    private function parseReceiptResponse(array $response): ReceiptData
    {
        $rawXml = null;

        if (isset($response['nfseXmlGZipB64']) && is_string($response['nfseXmlGZipB64'])) {
            $rawXml = GzipBase64::decode($response['nfseXmlGZipB64'], 'SEFIN NFS-e XML response');
        }

        $number = trim((string) ($response['nNFSe'] ?? $response['numero'] ?? ''));
        if ($number === '') {
            $number = $this->invoiceNumberFromXml($rawXml);
        }
        $accessKey = is_string($response['chaveAcesso'] ?? null)
            ? trim($response['chaveAcesso'])
            : '';

        if ($accessKey === '' || $number === '') {
            throw new NetworkException(
                'SEFIN returned an incomplete NFS-e receipt (missing '
                    . ($accessKey === '' ? 'chaveAcesso' : 'nNFSe')
                    . '). Recover using the DPS identifier before attempting another emission.',
                NfseErrorCode::InvalidResponse,
            );
        }

        return new ReceiptData(
            nfseNumber:        $number,
            chaveAcesso:       $accessKey,
            dataEmissao:       (string) ($response['dhEmi'] ?? $response['dataHoraProcessamento'] ?? $response['dataEmissao'] ?? ''),
            codigoVerificacao: isset($response['codigoVerificacao']) ? (string) $response['codigoVerificacao'] : null,
            rawXml:            $rawXml,
        );
    }

    private function invoiceNumberFromXml(?string $xml): string
    {
        if ($xml === null || trim($xml) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument();
            if (!$document->loadXML($xml, LIBXML_NONET)) {
                return '';
            }

            $xpath = new \DOMXPath($document);
            $numberNodes = $xpath->query(
                '/*[local-name()="NFSe"]/*[local-name()="infNFSe"]/*[local-name()="nNFSe"]'
            );
            if ($numberNodes === false) {
                return '';
            }

            return trim((string) $numberNodes->item(0)?->textContent);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
