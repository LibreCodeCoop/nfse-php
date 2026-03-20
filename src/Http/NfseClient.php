<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Http;

use LibreCodeCoop\NfsePHP\Contracts\NfseClientInterface;
use LibreCodeCoop\NfsePHP\Contracts\SecretStoreInterface;
use LibreCodeCoop\NfsePHP\Contracts\XmlSignerInterface;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\ReceiptData;
use LibreCodeCoop\NfsePHP\Exception\NfseException;
use LibreCodeCoop\NfsePHP\Xml\DpsSigner;
use LibreCodeCoop\NfsePHP\Xml\XmlBuilder;

/**
 * HTTP client for the SEFIN Nacional NFS-e REST API.
 *
 * Communicates with the SEFIN gateway to issue, query, and cancel NFS-e.
 * All requests carry a signed DPS XML payload.
 *
 * Gateway sandbox base URL: https://hml.nfse.fazenda.gov.br/NFS-e/api/v1
 * Gateway production base URL: https://nfse.fazenda.gov.br/NFS-e/api/v1
 */
class NfseClient implements NfseClientInterface
{
    private const BASE_URL_PROD    = 'https://nfse.fazenda.gov.br/NFS-e/api/v1';
    private const BASE_URL_SANDBOX = 'https://hml.nfse.fazenda.gov.br/NFS-e/api/v1';

    private readonly string $baseUrl;
    private readonly XmlSignerInterface $signer;

    public function __construct(
        private readonly SecretStoreInterface $secretStore,
        private readonly bool $sandboxMode = false,
        ?string $baseUrlOverride = null,
        ?XmlSignerInterface $signer = null,
    ) {
        $this->baseUrl = $baseUrlOverride ?? ($sandboxMode ? self::BASE_URL_SANDBOX : self::BASE_URL_PROD);
        $this->signer  = $signer ?? new DpsSigner($secretStore);
    }

    public function emit(DpsData $dps): ReceiptData
    {
        $xml    = (new XmlBuilder())->buildDps($dps);
        $signed = $this->signer->sign($xml, $dps->cnpjPrestador);

        $response = $this->post('/dps', $signed);

        return $this->parseReceiptResponse($response);
    }

    public function query(string $chaveAcesso): ReceiptData
    {
        $response = $this->get('/dps/' . $chaveAcesso);

        return $this->parseReceiptResponse($response);
    }

    public function cancel(string $chaveAcesso, string $motivo): bool
    {
        $this->delete('/dps/' . $chaveAcesso, $motivo);

        return true;
    }

    // -------------------------------------------------------------------------
    // Internal HTTP helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function post(string $path, string $xmlPayload): array
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/xml\r\nAccept: application/json\r\n",
                'content' => $xmlPayload,
                'ignore_errors' => true,
            ],
        ]);

        return $this->request($path, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => "Accept: application/json\r\n",
                'ignore_errors' => true,
            ],
        ]);

        return $this->request($path, $context);
    }

    private function delete(string $path, string $motivo): void
    {
        $payload = json_encode(['motivo' => $motivo], JSON_THROW_ON_ERROR);
        $context = stream_context_create([
            'http' => [
                'method'        => 'DELETE',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => $payload,
                'ignore_errors' => true,
            ],
        ]);

        $this->request($path, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $path, mixed $context): array
    {
        $url  = $this->baseUrl . $path;
        $body = file_get_contents($url, false, $context);

        if ($body === false) {
            throw new NfseException('Failed to connect to SEFIN gateway at ' . $url);
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new NfseException('Unexpected response format from SEFIN gateway');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseReceiptResponse(array $response): ReceiptData
    {
        return new ReceiptData(
            nfseNumber:        (string) ($response['nNFSe'] ?? $response['numero'] ?? ''),
            chaveAcesso:       (string) ($response['chaveAcesso'] ?? $response['id'] ?? ''),
            dataEmissao:       (string) ($response['dhEmi'] ?? $response['dataEmissao'] ?? ''),
            codigoVerificacao: isset($response['codigoVerificacao']) ? (string) $response['codigoVerificacao'] : null,
            rawXml:            null,
        );
    }
}
