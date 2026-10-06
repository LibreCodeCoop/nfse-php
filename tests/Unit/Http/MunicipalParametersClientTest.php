<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Http;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Config\MunicipalParametersConfig;
use LibreCodeCoop\NfsePHP\Exception\QueryException;
use LibreCodeCoop\NfsePHP\Http\MunicipalParametersClient;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Http\MunicipalParametersClient
 * @covers \LibreCodeCoop\NfsePHP\Config\MunicipalParametersConfig
 */
final class MunicipalParametersClientTest extends TestCase
{
    private static MockWebServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = new MockWebServer();
        self::$server->start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testConvenioUsesOfficialPath(): void
    {
        self::$server->setResponseOfPath(
            '/parametrizacao/3303302/convenio',
            new Response('{"parametros":{"tipoConvenio":1}}', ['Content-Type' => 'application/json'], 200),
        );

        $result = $this->client()->convenio('3303302');

        self::assertSame(1, $result['parametros']['tipoConvenio'] ?? null);
        self::assertSame('/parametrizacao/3303302/convenio', self::$server->getLastRequest()?->getRequestUri());
    }

    public function testAliquotaNormalizesServiceCodeAndPreservesCompetence(): void
    {
        self::$server->setResponseOfPath(
            '/parametrizacao/3303302/115022000/2026-10-03/aliquota',
            new Response('{"aliquotas":[{"Aliq":5.0}]}', ['Content-Type' => 'application/json'], 200),
        );

        $result = $this->client()->aliquota('3303302', '1.1502.20.00', '2026-10-03');

        self::assertSame(5.0, $result['aliquotas'][0]['Aliq'] ?? null);
        self::assertSame(
            '/parametrizacao/3303302/115022000/2026-10-03/aliquota',
            self::$server->getLastRequest()?->getRequestUri(),
        );
    }

    public function testRegimesRetencoesAndBenefitUseOfficialPaths(): void
    {
        self::$server->setResponseOfPath(
            '/parametrizacao/3303302/115022000/2026-10-03/regimes_especiais',
            new Response('{"regimes":[]}', ['Content-Type' => 'application/json'], 200),
        );
        $this->client()->regimesEspeciais('3303302', '115022000', '2026-10-03');
        self::assertSame(
            '/parametrizacao/3303302/115022000/2026-10-03/regimes_especiais',
            self::$server->getLastRequest()?->getRequestUri(),
        );

        self::$server->setResponseOfPath(
            '/parametrizacao/3303302/2026-10-03/retencoes',
            new Response('{"retencoes":[]}', ['Content-Type' => 'application/json'], 200),
        );
        $this->client()->retencoes('3303302', '2026-10-03');
        self::assertSame(
            '/parametrizacao/3303302/2026-10-03/retencoes',
            self::$server->getLastRequest()?->getRequestUri(),
        );

        self::$server->setResponseOfPath(
            '/parametrizacao/3303302/BEN-1/2026-10-03/beneficio',
            new Response('{"beneficio":{"codigoBeneficio":"BEN-1"}}', ['Content-Type' => 'application/json'], 200),
        );
        $result = $this->client()->beneficio('3303302', 'BEN-1', '2026-10-03');

        self::assertSame('BEN-1', $result['beneficio']['codigoBeneficio'] ?? null);
    }

    public function testRejectsInvalidInputsBeforeHttpRequest(): void
    {
        $client = $this->client();

        foreach ([
            fn () => $client->convenio('123'),
            fn () => $client->aliquota('3303302', '', '2026-10-03'),
            fn () => $client->aliquota('3303302', '010101', '2026-10-03'),
            fn () => $client->retencoes('3303302', 'not-a-date'),
            fn () => $client->beneficio('3303302', '', '2026-10-03'),
        ] as $operation) {
            try {
                $operation();
                self::fail('Expected InvalidArgumentException');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testHttpErrorPreservesUpstreamPayload(): void
    {
        self::$server->setResponseOfPath(
            '/parametrizacao/3303302/convenio',
            new Response('{"erros":[{"Codigo":"E001"}]}', ['Content-Type' => 'application/json'], 404),
        );

        try {
            $this->client()->convenio('3303302');
            self::fail('Expected QueryException');
        } catch (QueryException $e) {
            self::assertSame(404, $e->httpStatus);
            self::assertSame('E001', $e->upstreamPayload['erros'][0]['Codigo'] ?? null);
        }
    }

    public function testConfigurationUsesOfficialHostsAndValidatesTimeout(): void
    {
        self::assertSame(
            'https://adn.nfse.gov.br/parametrizacao',
            (new MunicipalParametersConfig())->baseUrl,
        );
        self::assertSame(
            'https://adn.producaorestrita.nfse.gov.br/parametrizacao',
            (new MunicipalParametersConfig(sandboxMode: true))->baseUrl,
        );

        $this->expectException(\InvalidArgumentException::class);
        new MunicipalParametersConfig(timeoutSeconds: 0);
    }

    private function client(): MunicipalParametersClient
    {
        return new MunicipalParametersClient(
            config: new MunicipalParametersConfig(
                sandboxMode: true,
                baseUrl: self::$server->getServerRoot() . '/parametrizacao',
                timeoutSeconds: 5,
            ),
            cert: new CertConfig(
                cnpj: '29842527000145',
                pfxPath: '/dev/null',
                vaultPath: 'secret/nfse/29842527000145',
            ),
        );
    }
}
