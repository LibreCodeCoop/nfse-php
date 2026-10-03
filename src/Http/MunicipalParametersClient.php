<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Http;

use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Config\MunicipalParametersConfig;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;
use LibreCodeCoop\NfsePHP\Exception\NfseErrorCode;
use LibreCodeCoop\NfsePHP\Exception\QueryException;

/**
 * Read-only client for the ADN municipal parameters API.
 *
 * Responses are intentionally returned as decoded arrays because each official
 * endpoint has a different versioned contract. Callers should validate the
 * fields they consume instead of assuming every municipality publishes every
 * optional parameter.
 */
final class MunicipalParametersClient
{
    public function __construct(
        private readonly MunicipalParametersConfig $config,
        private readonly CertConfig $cert,
    ) {
    }

    /** @return array<string, mixed> */
    public function convenio(string|int $codigoMunicipio): array
    {
        return $this->get('/' . $this->municipality($codigoMunicipio) . '/convenio');
    }

    /** @return array<string, mixed> */
    public function aliquota(string|int $codigoMunicipio, string $codigoServico, string $competencia): array
    {
        return $this->get(sprintf(
            '/%s/%s/%s/aliquota',
            $this->municipality($codigoMunicipio),
            rawurlencode($this->serviceCode($codigoServico)),
            rawurlencode($this->competence($competencia)),
        ));
    }

    /** @return array<string, mixed> */
    public function regimesEspeciais(string|int $codigoMunicipio, string $codigoServico, string $competencia): array
    {
        return $this->get(sprintf(
            '/%s/%s/%s/regimes_especiais',
            $this->municipality($codigoMunicipio),
            rawurlencode($this->serviceCode($codigoServico)),
            rawurlencode($this->competence($competencia)),
        ));
    }

    /** @return array<string, mixed> */
    public function retencoes(string|int $codigoMunicipio, string $competencia): array
    {
        return $this->get(sprintf(
            '/%s/%s/retencoes',
            $this->municipality($codigoMunicipio),
            rawurlencode($this->competence($competencia)),
        ));
    }

    /** @return array<string, mixed> */
    public function beneficio(string|int $codigoMunicipio, string $numeroBeneficio, string $competencia): array
    {
        $numeroBeneficio = trim($numeroBeneficio);

        if ($numeroBeneficio === '') {
            throw new \InvalidArgumentException('Municipal benefit number cannot be empty.');
        }

        return $this->get(sprintf(
            '/%s/%s/%s/beneficio',
            $this->municipality($codigoMunicipio),
            rawurlencode($numeroBeneficio),
            rawurlencode($this->competence($competencia)),
        ));
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
                'ignore_errors' => true,
                'timeout' => $this->config->timeoutSeconds,
            ],
            'ssl' => $this->sslContextOptions(),
        ]);

        $url = $this->config->baseUrl . $path;
        $http_response_header = [];
        $body = file_get_contents($url, false, $context);
        $httpStatus = $this->parseHttpStatus($http_response_header);

        if ($body === false && $httpStatus === 0) {
            throw new NetworkException('Failed to connect to ADN municipal parameters API at ' . $url);
        }

        if ($body === false) {
            $body = '';
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new NetworkException(
                'Unexpected non-JSON response from ADN municipal parameters API.',
                NfseErrorCode::InvalidResponse,
                $e,
            );
        }

        if (!is_array($decoded)) {
            throw new NetworkException(
                'Unexpected response format from ADN municipal parameters API.',
                NfseErrorCode::InvalidResponse,
            );
        }

        if ($httpStatus >= 400) {
            throw new QueryException(
                'ADN municipal parameters API returned error (HTTP ' . $httpStatus . ')',
                NfseErrorCode::QueryFailed,
                $httpStatus,
                $decoded,
            );
        }

        return $decoded;
    }

    private function municipality(string|int $codigoMunicipio): string
    {
        $value = trim((string) $codigoMunicipio);

        if (!preg_match('/^\d{7}$/', $value)) {
            throw new \InvalidArgumentException('Municipality code must contain exactly 7 digits.');
        }

        return $value;
    }

    private function serviceCode(string $codigoServico): string
    {
        $value = preg_replace('/[^0-9]/', '', $codigoServico) ?? '';

        if ($value === '') {
            throw new \InvalidArgumentException('Service code cannot be empty.');
        }

        return $value;
    }

    private function competence(string $competencia): string
    {
        $value = trim($competencia);

        try {
            new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Competence must be a valid date or date-time.', 0, $e);
        }

        return $value;
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

    /** @param list<string> $headers */
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
