<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Danfse\XmlToArray;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Danfse\XmlToArray
 */
class XmlToArrayTest extends TestCase
{
    private function fixture(): string
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/nfse_exemplo.xml');
        self::assertNotFalse($xml);

        return $xml;
    }

    public function testConvertsNestedElementsAndAttributes(): void
    {
        $nfseData = (new XmlToArray())->convert($this->fixture());

        // Root attribute is captured
        self::assertSame('1.01', $nfseData['versao']);

        $inf = $nfseData['infNFSe'];
        // Id attribute on infNFSe
        self::assertSame('NFS3303302112233450000195000000000000100000000001', $inf['Id']);
        self::assertSame('10', $inf['nNFSe']);
        self::assertSame('Niterói', $inf['xLocEmi']);

        // Nested leaf text
        self::assertSame('EMPRESA EXEMPLO DESENVOLVIMENTO LTDA', $inf['emit']['xNome']);
        self::assertSame('11222333000181', $inf['emit']['CNPJ']);
        self::assertSame('24020005', $inf['emit']['enderNac']['CEP']);

        // Deeply nested under DPS/infDPS
        self::assertSame('1', $inf['DPS']['infDPS']['tpAmb']);
        self::assertSame('CLIENTE FICTICIO COMERCIO S.A.', $inf['DPS']['infDPS']['toma']['xNome']);
        self::assertSame('1500.00', $inf['DPS']['infDPS']['valores']['vServPrest']['vServ']);
    }

    public function testIgnoresDigitalSignature(): void
    {
        $signed = str_replace(
            '</NFSe>',
            '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignatureValue>abc</SignatureValue></Signature></NFSe>',
            $this->fixture(),
        );

        $nfseData = (new XmlToArray())->convert($signed);

        self::assertArrayNotHasKey('Signature', $nfseData);
        self::assertArrayHasKey('infNFSe', $nfseData);
    }
}
