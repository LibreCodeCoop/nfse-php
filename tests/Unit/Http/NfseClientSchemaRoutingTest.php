<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Http;

use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Config\EnvironmentConfig;
use LibreCodeCoop\NfsePHP\Contracts\HttpTransportInterface;
use LibreCodeCoop\NfsePHP\Contracts\XmlSignerInterface;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\HttpRequestData;
use LibreCodeCoop\NfsePHP\Dto\HttpResponseData;
use LibreCodeCoop\NfsePHP\Http\NfseClient;
use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\DpsSchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;

final class NfseClientSchemaRoutingTest extends TestCase
{
    #[DataProvider('environments')]
    public function testPostUsesSchemaReleaseForSefinEnvironment(bool $sandbox, string $expectedRelease): void
    {
        $transport = new class () implements HttpTransportInterface {
            public ?HttpRequestData $captured = null;

            public function request(HttpRequestData $request): HttpResponseData
            {
                $this->captured = $request;

                return new HttpResponseData(201, json_encode([
                    'nNFSe' => '42',
                    'chaveAcesso' => 'synthetic-key',
                    'dataHoraProcessamento' => '2026-10-20T10:00:00-03:00',
                    'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe>synthetic</NFSe>')),
                ], JSON_THROW_ON_ERROR));
            }
        };

        $signer = new class () implements XmlSignerInterface {
            public function sign(string $xml, string $cnpj): string
            {
                return $xml;
            }
        };

        $client = new NfseClient(
            environment: new EnvironmentConfig(
                sandboxMode: $sandbox,
                baseUrl: 'https://stub.invalid/SefinNacional',
            ),
            cert: new CertConfig(
                cnpj: '11222333000181',
                pfxPath: '/not-used.pfx',
                vaultPath: 'secret/test',
            ),
            secretStore: new NoOpSecretStore(),
            signer: $signer,
            transport: $transport,
        );

        $client->emit(new DpsData(
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0101',
            valorServico: '1500.00',
            aliquota: '2.00',
            discriminacao: 'Servico ficticio',
            dataCompetencia: '2026-10-06',
            codigoTributacaoNacional: '010101',
            codigoTributacaoMunicipal: '001',
            codigoNbs: '115011000',
            ibsCbsFinalidade: 0,
            ibsCbsIndFinal: 0,
            ibsCbsCodigoIndicadorOperacao: '100301',
            ibsCbsIndDest: 0,
            ibsCbsCst: '000',
            ibsCbsClassificacaoTributaria: '000001',
        ));

        self::assertInstanceOf(HttpRequestData::class, $transport->captured);
        self::assertSame('POST', $transport->captured->method);
        self::assertSame('https://stub.invalid/SefinNacional/nfse', $transport->captured->url);
        self::assertIsString($transport->captured->body);
        $payload = json_decode($transport->captured->body, true, 512, JSON_THROW_ON_ERROR);
        $xml = gzdecode(base64_decode($payload['dpsXmlGZipB64'], true));
        self::assertIsString($xml);
        self::assertSame([], (new DpsSchemaValidator(schemaRelease: $expectedRelease))->validate($xml));

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml, LIBXML_NONET));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('nfse', 'http://www.sped.fazenda.gov.br/nfse');
        self::assertSame(
            $sandbox ? '0' : '',
            $xpath->evaluate('string(/nfse:DPS/nfse:infDPS/nfse:finNFSe)'),
        );
    }

    /** @return array<string, array{bool, string}> */
    public static function environments(): array
    {
        return [
            'production 20260209' => [false, EnvironmentConfig::SCHEMA_RELEASE_PRODUCTION_20260209],
            'restricted 20261020' => [true, EnvironmentConfig::SCHEMA_RELEASE_RESTRICTED_20261020],
        ];
    }
}
