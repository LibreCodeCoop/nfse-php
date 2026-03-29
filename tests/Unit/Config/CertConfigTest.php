<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Config;

use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Config\CertConfig
 */
class CertConfigTest extends TestCase
{
    public function testStoresAllProperties(): void
    {
        $config = new CertConfig(
            cnpj:      '29842527000145',
            pfxPath:   '/etc/nfse/certs/company.pfx',
            vaultPath: 'secret/nfse/29842527000145',
            transportCertificatePath: '/etc/nfse/certs/client.crt.pem',
            transportPrivateKeyPath: '/etc/nfse/certs/client.key.pem',
        );

        self::assertSame('29842527000145', $config->cnpj);
        self::assertSame('/etc/nfse/certs/company.pfx', $config->pfxPath);
        self::assertSame('secret/nfse/29842527000145', $config->vaultPath);
        self::assertSame('/etc/nfse/certs/client.crt.pem', $config->transportCertificatePath);
        self::assertSame('/etc/nfse/certs/client.key.pem', $config->transportPrivateKeyPath);
    }

    public function testCnpjIsReadonly(): void
    {
        $config = new CertConfig(
            cnpj:      '29842527000145',
            pfxPath:   '/etc/nfse/certs/company.pfx',
            vaultPath: 'secret/nfse/29842527000145',
        );

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line */
        $config->cnpj = 'other';
    }

    public function testPfxPathIsReadonly(): void
    {
        $config = new CertConfig(
            cnpj:      '29842527000145',
            pfxPath:   '/etc/nfse/certs/company.pfx',
            vaultPath: 'secret/nfse/29842527000145',
        );

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line */
        $config->pfxPath = 'other';
    }

    public function testVaultPathIsReadonly(): void
    {
        $config = new CertConfig(
            cnpj:      '29842527000145',
            pfxPath:   '/etc/nfse/certs/company.pfx',
            vaultPath: 'secret/nfse/29842527000145',
        );

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line */
        $config->vaultPath = 'other';
    }

    public function testTransportCertificatePathIsReadonly(): void
    {
        $config = new CertConfig(
            cnpj:      '29842527000145',
            pfxPath:   '/etc/nfse/certs/company.pfx',
            vaultPath: 'secret/nfse/29842527000145',
            transportCertificatePath: '/etc/nfse/certs/client.crt.pem',
        );

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line */
        $config->transportCertificatePath = 'other';
    }

    public function testTransportPrivateKeyPathIsReadonly(): void
    {
        $config = new CertConfig(
            cnpj:      '29842527000145',
            pfxPath:   '/etc/nfse/certs/company.pfx',
            vaultPath: 'secret/nfse/29842527000145',
            transportPrivateKeyPath: '/etc/nfse/certs/client.key.pem',
        );

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line */
        $config->transportPrivateKeyPath = 'other';
    }
}
