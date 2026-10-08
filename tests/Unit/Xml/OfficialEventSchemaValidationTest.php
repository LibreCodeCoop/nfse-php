<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\EventSchemaValidator;

/**
 * Contract tests against the official Sistema Nacional NFS-e event v1.01 XSD.
 *
 * @covers \LibreCodeCoop\NfsePHP\Xml\EventSchemaValidator
 */
final class OfficialEventSchemaValidationTest extends TestCase
{
    private EventSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new EventSchemaValidator();
    }

    public function testCancellationRequestMatchesOfficialSchema(): void
    {
        self::assertSame([], $this->validator->validate($this->cancellationXml()));
    }

    public function testValidatorRejectsUnsupportedSchemaVersionExplicitly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported NFS-e event schema version: 9.99');

        new EventSchemaValidator(schemaVersion: '9.99');
    }

    public function testValidatorRejectsUnknownEventElement(): void
    {
        $invalidXml = str_replace('e101101', 'e999999', $this->cancellationXml());

        self::assertNotSame([], $this->validator->validate($invalidXml));
    }

    /**
     * @dataProvider cancellationReasonLengthCases
     */
    public function testCancellationReasonLengthMatchesOfficialSchema(int $length, bool $valid): void
    {
        $xml = str_replace(
            'Erro de emissao confirmado pelo prestador',
            str_repeat('a', $length),
            $this->cancellationXml(),
        );

        $errors = $this->validator->validate($xml);

        $valid ? self::assertSame([], $errors) : self::assertNotSame([], $errors);
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function cancellationReasonLengthCases(): array
    {
        return [
            '14 rejected' => [14, false],
            '15 accepted' => [15, true],
            '255 accepted' => [255, true],
            '256 rejected' => [256, false],
        ];
    }

    public function testValidatorRejectsWrongEventElementOrder(): void
    {
        $invalidXml = str_replace(
            '<cMotivo>1</cMotivo><xMotivo>Erro de emissao confirmado pelo prestador</xMotivo>',
            '<xMotivo>Erro de emissao confirmado pelo prestador</xMotivo><cMotivo>1</cMotivo>',
            $this->cancellationXml(),
        );

        self::assertNotSame([], $this->validator->validate($invalidXml));
    }

    public function testMalformedXmlDoesNotResolveExternalNetworkResources(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE pedRegEvento SYSTEM "https://example.invalid/event.dtd"><pedRegEvento/>';

        self::assertNotSame([], $this->validator->validate($xml));
    }

    private function cancellationXml(): string
    {
        $accessKey = str_repeat('1', 50);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<pedRegEvento xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01">'
            . '<infPedReg Id="PRE' . $accessKey . '101101">'
            . '<tpAmb>2</tpAmb>'
            . '<verAplic>nfse-php-test</verAplic>'
            . '<dhEvento>2026-10-04T12:00:00-03:00</dhEvento>'
            . '<CNPJAutor>11222333000181</CNPJAutor>'
            . '<chNFSe>' . $accessKey . '</chNFSe>'
            . '<e101101>'
            . '<xDesc>Cancelamento de NFS-e</xDesc>'
            . '<cMotivo>1</cMotivo>'
            . '<xMotivo>Erro de emissao confirmado pelo prestador</xMotivo>'
            . '</e101101>'
            . '</infPedReg>'
            . '</pedRegEvento>';
    }
}
