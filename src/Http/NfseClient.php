<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Http;

use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Config\EnvironmentConfig;
use LibreCodeCoop\NfsePHP\Contracts\NfseClientInterface;
use LibreCodeCoop\NfsePHP\Contracts\SecretStoreInterface;
use LibreCodeCoop\NfsePHP\Contracts\XmlSignerInterface;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\ReceiptData;
use LibreCodeCoop\NfsePHP\Exception\CancellationException;
use LibreCodeCoop\NfsePHP\Exception\IssuanceException;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;
use LibreCodeCoop\NfsePHP\Exception\NfseErrorCode;
use LibreCodeCoop\NfsePHP\Exception\QueryException;
use LibreCodeCoop\NfsePHP\Xml\DpsSigner;
use LibreCodeCoop\NfsePHP\Xml\XmlBuilder;

/**
 * HTTP client for the SEFIN Nacional NFS-e REST API.
 *
 * Communicates with the SEFIN gateway to issue, query, and cancel NFS-e.
 * All requests carry a signed DPS XML payload.
 */
class NfseClient implements NfseClientInterface
{
    private readonly string $baseUrl;
    private readonly XmlSignerInterface $signer;

    public function __construct(
        private readonly EnvironmentConfig $environment,
        private readonly CertConfig $cert,
        private readonly SecretStoreInterface $secretStore,
        ?XmlSignerInterface $signer = null,
    ) {
        $this->baseUrl = $environment->baseUrl;
        $this->signer  = $signer ?? new DpsSigner($secretStore);
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

    public function cancel(string $chaveAcesso, string $motivo): bool
    {
        [$httpStatus, $body] = $this->delete('/dps/' . $chaveAcesso, $motivo);

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

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => $payload,
                'ignore_errors' => true,
            ],
            'ssl' => $this->sslContextOptions(),
        ]);

        return $this->fetchAndDecode($path, $context);
    }

    /**
     * @return array{int, array<string, mixed>}
     */
    private function get(string $path): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
            'ssl' => $this->sslContextOptions(),
        ]);

        return $this->fetchAndDecode($path, $context);
    }

    /**
     * @return array{int, array<string, mixed>}
     */
    private function delete(string $path, string $motivo): array
    {
        $payload = json_encode(['motivo' => $motivo], JSON_THROW_ON_ERROR);
        $context = stream_context_create([
            'http' => [
                'method'        => 'DELETE',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => $payload,
                'ignore_errors' => true,
            ],
            'ssl' => $this->sslContextOptions(),
        ]);

        return $this->fetchAndDecode($path, $context);
    }

    /**
     * @return array<string, bool|string>
     */
    private function sslContextOptions(): array
    {
        $options = [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ];

        if ($this->cert->transportCertificatePath !== null && $this->cert->transportPrivateKeyPath !== null) {
            $options['local_cert'] = $this->cert->transportCertificatePath;
            $options['local_pk'] = $this->cert->transportPrivateKeyPath;
        }

        return $options;
    }

    /**
     * Perform the raw HTTP request and decode the JSON body.
     *
     * PHP sets $http_response_header in the calling scope when file_get_contents
     * uses an HTTP wrapper. We initialize it to [] so static analysers have a
     * typed baseline; the HTTP wrapper will overwrite it on a successful
     * connection, even when the server responds with 4xx/5xx.
     *
     * @return array{int, array<string, mixed>}
     */
    private function fetchAndDecode(string $path, mixed $context): array
    {
        $url = $this->baseUrl . $path;

        $http_response_header = [];
        $body                 = file_get_contents($url, false, $context);
        $httpStatus           = $this->parseHttpStatus($http_response_header);

        if ($body === false) {
            throw new NetworkException('Failed to connect to SEFIN gateway at ' . $url);
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new NetworkException(
                'Unexpected response format from SEFIN gateway',
                NfseErrorCode::InvalidResponse,
            );
        }

        return [$httpStatus, $decoded];
    }

    /**
     * Extract the HTTP status code from the first response header line.
     *
     * @param list<string> $headers
     */
    private function parseHttpStatus(array $headers): int
    {
        if (!isset($headers[0])) {
            return 0;
        }

        if (preg_match('/HTTP\/[\d.]+ (\d{3})/', $headers[0], $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseReceiptResponse(array $response): ReceiptData
    {
        $rawXml = null;

        if (isset($response['nfseXmlGZipB64']) && is_string($response['nfseXmlGZipB64'])) {
            $decodedXml = base64_decode($response['nfseXmlGZipB64'], true);

            if ($decodedXml !== false) {
                $inflatedXml = gzdecode($decodedXml);

                if ($inflatedXml !== false) {
                    $rawXml = $inflatedXml;
                }
            }
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
