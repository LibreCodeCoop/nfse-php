<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Support;

use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Contracts\SecretStoreInterface;
use LibreCodeCoop\NfsePHP\Exception\PfxImportException;

/**
 * Builds temporary PEM files for mTLS transport from the configured PFX.
 *
 * Runtime requests should use the same certificate bundle stored in module
 * settings, while still allowing explicit PEM overrides for isolated tests.
 */
final class TemporaryTlsFilesFactory
{
    public function __construct(
        private readonly SecretStoreInterface $secretStore,
    ) {
    }

    /**
     * @param array<string, mixed> $baseOptions
     * @return array{0: array<string, mixed>, 1: \Closure(): void}
     */
    public function create(CertConfig $cert, array $baseOptions = []): array
    {
        if ($cert->transportCertificatePath !== null && $cert->transportPrivateKeyPath !== null) {
            $options = $baseOptions;
            $options['local_cert'] = $cert->transportCertificatePath;
            $options['local_pk'] = $cert->transportPrivateKeyPath;

            return [$options, static function (): void {
            }];
        }

        $secret = $this->secretStore->get($this->secretPath($cert));
        $pfxPath = (string) ($secret['pfx_path'] ?? $cert->pfxPath);
        $password = (string) ($secret['password'] ?? '');

        if ($pfxPath === '' || !is_file($pfxPath)) {
            throw new PfxImportException('PFX file not found for CNPJ ' . $cert->cnpj);
        }

        $pfxContent = file_get_contents($pfxPath);

        if ($pfxContent === false) {
            throw new PfxImportException('Cannot read PFX file for CNPJ ' . $cert->cnpj);
        }

        [$privateKeyPem, $certificatePem] = $this->importPfx($pfxContent, $password, $cert->cnpj);
        [$certificatePath, $privateKeyPath] = $this->writeTemporaryPemFiles($certificatePem, $privateKeyPem);

        $options = $baseOptions;
        $options['local_cert'] = $certificatePath;
        $options['local_pk'] = $privateKeyPath;

        return [$options, static function () use ($certificatePath, $privateKeyPath): void {
            if (is_file($certificatePath)) {
                unlink($certificatePath);
            }

            if (is_file($privateKeyPath)) {
                unlink($privateKeyPath);
            }
        }];
    }

    private function secretPath(CertConfig $cert): string
    {
        return $cert->vaultPath !== '' ? $cert->vaultPath : 'pfx/' . $cert->cnpj;
    }

    /**
     * @return array{string, string} [privateKeyPem, certificatePem]
     */
    private function importPfx(string $pfxContent, string $password, string $cnpj): array
    {
        $certs = [];
        $ok = openssl_pkcs12_read($pfxContent, $certs, $password);

        if (!$ok) {
            $nativeErrors = $this->drainOpenSslErrors();

            try {
                return $this->extractLegacyPemMaterial($pfxContent, $password, $cnpj);
            } catch (PfxImportException $cliException) {
                $nativeError = $nativeErrors !== [] ? implode(' | ', $nativeErrors) : 'unknown OpenSSL error';

                throw new PfxImportException(
                    'Failed to import PFX for CNPJ ' . $cnpj . ': ' . $nativeError . ' (CLI fallback failed: ' . $cliException->getMessage() . ')',
                    previous: $cliException,
                );
            }
        }

        return [$certs['pkey'], $certs['cert']];
    }

    /**
     * @return list<string>
     */
    private function drainOpenSslErrors(): array
    {
        $errors = [];

        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * @return array{string, string} [privateKeyPem, certificatePem]
     */
    private function extractLegacyPemMaterial(string $pfxContent, string $password, string $cnpj): array
    {
        $tmpIn = tempnam(sys_get_temp_dir(), 'nfse_in_');
        $tmpOut = tempnam(sys_get_temp_dir(), 'nfse_out_');

        if ($tmpIn === false || $tmpOut === false) {
            throw new PfxImportException('Failed to allocate temporary files for OpenSSL repack');
        }

        try {
            file_put_contents($tmpIn, $pfxContent);
            putenv('NFSE_PFX_PASS=' . $password);

            $cmd = sprintf(
                'openssl pkcs12 -legacy -in %s -passin env:NFSE_PFX_PASS -nodes -out %s 2>/dev/null',
                escapeshellarg($tmpIn),
                escapeshellarg($tmpOut),
            );

            $output = [];
            $code = 0;
            exec($cmd, $output, $code);

            if ($code !== 0) {
                throw new PfxImportException('openssl CLI repack failed with exit code ' . $code);
            }

            $result = file_get_contents($tmpOut);

            if ($result === false || $result === '') {
                throw new PfxImportException('openssl CLI repack produced empty output');
            }

            return $this->extractPemParts($result, $cnpj);
        } finally {
            putenv('NFSE_PFX_PASS');

            if (is_file($tmpIn)) {
                unlink($tmpIn);
            }

            if (is_file($tmpOut)) {
                unlink($tmpOut);
            }
        }
    }

    /**
     * @return array{string, string} [privateKeyPem, certificatePem]
     */
    private function extractPemParts(string $pemBundle, string $cnpj): array
    {
        $privateKeyMatched = preg_match(
            '/-----BEGIN(?: ENCRYPTED)? PRIVATE KEY-----.*?-----END(?: ENCRYPTED)? PRIVATE KEY-----/s',
            $pemBundle,
            $privateKeyMatches,
        ) === 1;

        $certificateMatched = preg_match(
            '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
            $pemBundle,
            $certificateMatches,
        ) === 1;

        if (!$privateKeyMatched || !$certificateMatched) {
            throw new PfxImportException('Failed to extract PEM material from legacy PFX for CNPJ ' . $cnpj);
        }

        return [$privateKeyMatches[0], $certificateMatches[0]];
    }

    /**
     * @return array{string, string} [certificatePath, privateKeyPath]
     */
    private function writeTemporaryPemFiles(string $certificatePem, string $privateKeyPem): array
    {
        $certificatePath = tempnam(sys_get_temp_dir(), 'nfse_tls_cert_');
        $privateKeyPath = tempnam(sys_get_temp_dir(), 'nfse_tls_key_');

        if ($certificatePath === false || $privateKeyPath === false) {
            throw new PfxImportException('Failed to allocate temporary PEM files for mTLS transport');
        }

        try {
            $certificateBytes = file_put_contents($certificatePath, $certificatePem);
            $privateKeyBytes = file_put_contents($privateKeyPath, $privateKeyPem);

            if ($certificateBytes === false || $privateKeyBytes === false) {
                throw new PfxImportException('Failed to write temporary PEM files for mTLS transport');
            }

            chmod($certificatePath, 0o600);
            chmod($privateKeyPath, 0o600);

            return [$certificatePath, $privateKeyPath];
        } catch (\Throwable $throwable) {
            if (is_file($certificatePath)) {
                unlink($certificatePath);
            }

            if (is_file($privateKeyPath)) {
                unlink($privateKeyPath);
            }

            if ($throwable instanceof PfxImportException) {
                throw $throwable;
            }

            throw new PfxImportException('Failed to prepare temporary PEM files for mTLS transport', previous: $throwable);
        }
    }
}
