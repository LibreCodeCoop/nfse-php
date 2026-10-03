<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Http;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use LibreCodeCoop\NfsePHP\Config\AdnEnvironmentConfig;
use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;
use LibreCodeCoop\NfsePHP\Exception\QueryException;
use LibreCodeCoop\NfsePHP\Http\AdnClient;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * @covers \LibreCodeCoop\NfsePHP\Http\AdnClient
 * @covers \LibreCodeCoop\NfsePHP\Config\AdnEnvironmentConfig
 */
final class AdnClientTest extends TestCase
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

    public function testGetDfeNormalizesAndDecompressesOfficialResponse(): void
    {
        $payload = $this->distributionPayload([
            [
                'NSU' => 11,
                'ChaveAcesso' => str_repeat('1', 50),
                'TipoDocumento' => 'NFSE',
                'TipoEvento' => null,
                'ArquivoXml' => base64_encode(gzencode('<NFSe>ok</NFSe>')),
                'DataHoraGeracao' => '2026-06-15T10:30:00-03:00',
            ],
        ], [
            'UltimoNSU' => 12,
            'Alertas' => [
                [
                    'Codigo' => 'A001',
                    'Descricao' => 'Aviso',
                    'Complemento' => 'Teste',
                    'Parametros' => ['x'],
                ],
            ],
        ]);

        self::$server->setResponseOfPath(
            '/contribuintes/DFe/10',
            new Response(json_encode($payload, JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json'], 200),
        );

        $result = $this->makeClient()->getDfe(10, '12abc34501de35', false);

        self::assertSame('DOCUMENTOS_LOCALIZADOS', $result->statusProcessamento);
        self::assertSame('HOMOLOGACAO', $result->ambiente);
        self::assertSame(12, $result->ultimoNsu);
        self::assertCount(1, $result->documents);
        self::assertSame(11, $result->documents[0]->nsu);
        self::assertSame('<NFSe>ok</NFSe>', $result->documents[0]->xml);
        self::assertSame('A001', $result->alerts[0]->code);

        $request = self::$server->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('GET', $request->getRequestMethod());
        self::assertStringContainsString('/contribuintes/DFe/10?', $request->getRequestUri());
        self::assertStringContainsString('lote=false', $request->getRequestUri());
        self::assertStringContainsString('cnpjConsulta=12ABC34501DE35', $request->getRequestUri());
    }

    public function testListEventsUsesContributorAdnEndpoint(): void
    {
        self::$server->setResponseOfPath(
            '/contribuintes/NFSe/abc-123/Eventos',
            new Response(
                json_encode($this->distributionPayload([], ['StatusProcessamento' => 'NENHUM_DOCUMENTO_LOCALIZADO']), JSON_THROW_ON_ERROR),
                ['Content-Type' => 'application/json'],
                200,
            ),
        );

        $result = $this->makeClient()->listEvents('abc-123');

        self::assertSame('NENHUM_DOCUMENTO_LOCALIZADO', $result->statusProcessamento);
        self::assertSame([], $result->documents);

        $request = self::$server->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('/contribuintes/NFSe/abc-123/Eventos', $request->getRequestUri());
    }

    public function testRejectsNegativeNsu(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeClient()->getDfe(-1);
    }

    public function testRejectsInvalidQueryCnpj(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeClient()->getDfe(0, '123');
    }

    public function testHttpErrorsUseTypedQueryException(): void
    {
        self::$server->setResponseOfPath(
            '/contribuintes/DFe/999',
            new Response('{"StatusProcessamento":"REJEICAO","Erros":[]}', ['Content-Type' => 'application/json'], 404),
        );

        try {
            $this->makeClient()->getDfe(999);
            self::fail('Expected QueryException');
        } catch (QueryException $e) {
            self::assertSame(404, $e->httpStatus);
            self::assertSame('REJEICAO', $e->upstreamPayload['StatusProcessamento'] ?? null);
        }
    }

    public function testMalformedCompressedDocumentFailsFast(): void
    {
        $payload = $this->distributionPayload([
            [
                'NSU' => 1,
                'TipoDocumento' => 'NFSE',
                'ArquivoXml' => base64_encode('not-gzip'),
            ],
        ]);

        self::$server->setResponseOfPath(
            '/contribuintes/DFe/1',
            new Response(json_encode($payload, JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json'], 200),
        );

        $this->expectException(NetworkException::class);
        $this->makeClient()->getDfe(1);
    }

    public function testEnvironmentConfigUsesOfficialContributorHosts(): void
    {
        self::assertSame(
            'https://adn.nfse.gov.br/contribuintes',
            (new AdnEnvironmentConfig())->baseUrl,
        );
        self::assertSame(
            'https://adn.producaorestrita.nfse.gov.br/contribuintes',
            (new AdnEnvironmentConfig(sandboxMode: true))->baseUrl,
        );
    }

    public function testEnvironmentConfigRejectsNonPositiveTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AdnEnvironmentConfig(timeoutSeconds: 0);
    }

    private function makeClient(): AdnClient
    {
        return new AdnClient(
            environment: new AdnEnvironmentConfig(
                sandboxMode: true,
                baseUrl: self::$server->getServerRoot() . '/contribuintes',
                timeoutSeconds: 5,
            ),
            cert: new CertConfig(
                cnpj: '29842527000145',
                pfxPath: '/dev/null',
                vaultPath: 'secret/nfse/29842527000145',
            ),
        );
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function distributionPayload(array $documents, array $overrides = []): array
    {
        return array_replace([
            'StatusProcessamento' => 'DOCUMENTOS_LOCALIZADOS',
            'LoteDFe' => $documents,
            'Alertas' => [],
            'Erros' => [],
            'TipoAmbiente' => 'HOMOLOGACAO',
            'VersaoAplicativo' => '1.0.0',
            'DataHoraProcessamento' => '2026-06-15T10:31:00-03:00',
        ], $overrides);
    }
}
