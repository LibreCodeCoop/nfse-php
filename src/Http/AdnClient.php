<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Http;

use LibreCodeCoop\NfsePHP\Config\AdnEnvironmentConfig;
use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Dto\AdnDistributionData;
use LibreCodeCoop\NfsePHP\Dto\AdnDocumentData;
use LibreCodeCoop\NfsePHP\Dto\AdnMessageData;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;
use LibreCodeCoop\NfsePHP\Exception\NfseErrorCode;
use LibreCodeCoop\NfsePHP\Exception\QueryException;
use LibreCodeCoop\NfsePHP\Support\GzipBase64;

/**
 * Client for the ADN contributor distribution API.
 *
 * This API is separate from SEFIN issuance and distributes NFS-e/DPS/event
 * documents by NSU or by NFS-e access key.
 */
final class AdnClient
{
    public function __construct(
        private readonly AdnEnvironmentConfig $environment,
        private readonly CertConfig $cert,
    ) {
    }

    public function getDfe(int $nsu, ?string $cnpjConsulta = null, bool $lote = true): AdnDistributionData
    {
        if ($nsu < 0) {
            throw new \InvalidArgumentException('NSU cannot be negative.');
        }

        $query = ['lote' => $lote ? 'true' : 'false'];

        if ($cnpjConsulta !== null && trim($cnpjConsulta) !== '') {
            $normalizedCnpj = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $cnpjConsulta) ?? '');

            if (!preg_match('/^[A-Z0-9]{12}\d{2}$/', $normalizedCnpj)) {
                throw new \InvalidArgumentException('CNPJ de consulta must contain 14 valid alphanumeric CNPJ characters.');
            }

            $query['cnpjConsulta'] = $normalizedCnpj;
        }

        return $this->request(
            '/DFe/' . $nsu . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
        );
    }

    public function listEvents(string $chaveAcesso): AdnDistributionData
    {
        $chaveAcesso = trim($chaveAcesso);

        if ($chaveAcesso === '') {
            throw new \InvalidArgumentException('NFS-e access key cannot be empty.');
        }

        return $this->request('/NFSe/' . rawurlencode($chaveAcesso) . '/Eventos');
    }

    private function request(string $path): AdnDistributionData
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
                'ignore_errors' => true,
                'timeout' => $this->environment->timeoutSeconds,
            ],
            'ssl' => $this->sslContextOptions(),
        ]);

        $url = $this->environment->baseUrl . $path;
        $http_response_header = [];
        $body = file_get_contents($url, false, $context);
        $httpStatus = $this->parseHttpStatus($http_response_header);

        if ($body === false && $httpStatus === 0) {
            throw new NetworkException('Failed to connect to ADN contributor API at ' . $url);
        }

        if ($body === false) {
            $body = '';
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new NetworkException(
                'Unexpected non-JSON response from ADN contributor API.',
                NfseErrorCode::InvalidResponse,
                $e,
            );
        }

        if (!is_array($decoded)) {
            throw new NetworkException(
                'Unexpected response format from ADN contributor API.',
                NfseErrorCode::InvalidResponse,
            );
        }

        if ($httpStatus >= 400) {
            throw new QueryException(
                'ADN contributor API returned error (HTTP ' . $httpStatus . ')',
                NfseErrorCode::QueryFailed,
                $httpStatus,
                $decoded,
            );
        }

        return $this->parseDistribution($decoded);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseDistribution(array $response): AdnDistributionData
    {
        $rawDocuments = is_array($response['LoteDFe'] ?? null) ? $response['LoteDFe'] : [];
        $documents = [];

        foreach ($rawDocuments as $rawDocument) {
            if (!is_array($rawDocument)) {
                continue;
            }

            $documents[] = new AdnDocumentData(
                nsu: isset($rawDocument['NSU']) && is_numeric($rawDocument['NSU'])
                    ? (int) $rawDocument['NSU']
                    : null,
                chaveAcesso: isset($rawDocument['ChaveAcesso']) && is_scalar($rawDocument['ChaveAcesso'])
                    ? (string) $rawDocument['ChaveAcesso']
                    : null,
                tipoDocumento: isset($rawDocument['TipoDocumento']) && is_scalar($rawDocument['TipoDocumento'])
                    ? (string) $rawDocument['TipoDocumento']
                    : 'NENHUM',
                tipoEvento: isset($rawDocument['TipoEvento']) && is_scalar($rawDocument['TipoEvento'])
                    ? (string) $rawDocument['TipoEvento']
                    : null,
                xml: $this->decodeDocumentXml($rawDocument['ArquivoXml'] ?? null),
                dataHoraGeracao: isset($rawDocument['DataHoraGeracao']) && is_scalar($rawDocument['DataHoraGeracao'])
                    ? (string) $rawDocument['DataHoraGeracao']
                    : null,
            );
        }

        return new AdnDistributionData(
            statusProcessamento: isset($response['StatusProcessamento']) && is_scalar($response['StatusProcessamento'])
                ? (string) $response['StatusProcessamento']
                : '',
            documents: $documents,
            alerts: $this->parseMessages($response['Alertas'] ?? null),
            errors: $this->parseMessages($response['Erros'] ?? null),
            ambiente: isset($response['TipoAmbiente']) && is_scalar($response['TipoAmbiente'])
                ? (string) $response['TipoAmbiente']
                : '',
            versaoAplicativo: isset($response['VersaoAplicativo']) && is_scalar($response['VersaoAplicativo'])
                ? (string) $response['VersaoAplicativo']
                : null,
            dataHoraProcessamento: isset($response['DataHoraProcessamento']) && is_scalar($response['DataHoraProcessamento'])
                ? (string) $response['DataHoraProcessamento']
                : '',
            ultimoNsu: $this->readLastNsu($response),
        );
    }

    private function decodeDocumentXml(mixed $encoded): ?string
    {
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        return GzipBase64::decode($encoded, 'ADN document payload');
    }

    /**
     * @return list<AdnMessageData>
     */
    private function parseMessages(mixed $rawMessages): array
    {
        if (!is_array($rawMessages)) {
            return [];
        }

        $messages = [];

        foreach ($rawMessages as $rawMessage) {
            if (!is_array($rawMessage)) {
                continue;
            }

            $parameters = [];
            if (is_array($rawMessage['Parametros'] ?? null)) {
                foreach ($rawMessage['Parametros'] as $parameter) {
                    if (is_scalar($parameter)) {
                        $parameters[] = (string) $parameter;
                    }
                }
            }

            $messages[] = new AdnMessageData(
                code: isset($rawMessage['Codigo']) && is_scalar($rawMessage['Codigo'])
                    ? (string) $rawMessage['Codigo']
                    : null,
                description: isset($rawMessage['Descricao']) && is_scalar($rawMessage['Descricao'])
                    ? (string) $rawMessage['Descricao']
                    : null,
                complement: isset($rawMessage['Complemento']) && is_scalar($rawMessage['Complemento'])
                    ? (string) $rawMessage['Complemento']
                    : null,
                parameters: $parameters,
            );
        }

        return $messages;
    }

    /**
     * The current official contributor schema does not guarantee a pagination
     * cursor field, but deployed responses have used these casing variants.
     * Prefer the server cursor when present instead of inferring it from the
     * largest document NSU.
     *
     * @param array<string, mixed> $response
     */
    private function readLastNsu(array $response): ?int
    {
        foreach (['UltimoNSU', 'ultimoNSU', 'UltNSU', 'ultNSU'] as $key) {
            if (isset($response[$key]) && is_numeric($response[$key])) {
                return (int) $response[$key];
            }
        }

        return null;
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
     * @param list<string> $headers
     */
    private function parseHttpStatus(array $headers): int
    {
        if (!isset($headers[0])) {
            return 0;
        }

        if (preg_match('/HTTP\/[\d.]+ (\d{3})/', $headers[0], $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
