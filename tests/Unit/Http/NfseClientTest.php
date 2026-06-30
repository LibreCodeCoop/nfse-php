<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Http;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Config\EnvironmentConfig;
use LibreCodeCoop\NfsePHP\Contracts\XmlSignerInterface;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Exception\ArtifactException;
use LibreCodeCoop\NfsePHP\Exception\CancellationException;
use LibreCodeCoop\NfsePHP\Exception\IssuanceException;
use LibreCodeCoop\NfsePHP\Exception\NfseErrorCode;
use LibreCodeCoop\NfsePHP\Exception\QueryException;
use LibreCodeCoop\NfsePHP\Http\NfseClient;
use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

/**
 * Tests for NfseClient using donatj/mock-webserver (tier-1 — no real cert required).
 *
 * @covers \LibreCodeCoop\NfsePHP\Http\NfseClient
 */
class NfseClientTest extends TestCase
{
    private static MockWebServer $server;
    private XmlSignerInterface $signer;

    public static function setUpBeforeClass(): void
    {
        self::$server = new MockWebServer();
        self::$server->start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = new class () implements XmlSignerInterface {
            public function sign(string $xml, string $cnpj): string
            {
                return $xml;
            }
        };
    }

    public function testEmitReturnsReceiptDataOnSuccess(): void
    {
        $payload = json_encode([
            'nNFSe'          => '42',
            'chaveAcesso'    => 'abc-123',
            'dataHoraProcessamento' => '2026-01-01T12:00:00',
            'nfseXmlGZipB64' => base64_encode(gzencode('<NFS-e>ok</NFS-e>')),
        ], JSON_THROW_ON_ERROR);

        self::$server->setResponseOfPath(
            '/SefinNacional/nfse',
            new Response($payload, ['Content-Type' => 'application/json'], 201)
        );

        $client = $this->makeClient($this->signer);

        $dps     = $this->makeDps();
        $receipt = $client->emit($dps);

        self::assertSame('42', $receipt->nfseNumber);
        self::assertSame('abc-123', $receipt->chaveAcesso);
        self::assertSame('2026-01-01T12:00:00', $receipt->dataEmissao);
        self::assertSame('<NFS-e>ok</NFS-e>', $receipt->rawXml);
    }

    public function testEmitBuildsXmlWithTpAmbBeforeMunicipalityFields(): void
    {
        $payload = json_encode([
            'nNFSe' => '100',
            'chaveAcesso' => 'tpamb-order-ok',
            'dataHoraProcessamento' => '2026-01-02T10:00:00',
        ], JSON_THROW_ON_ERROR);

        self::$server->setResponseOfPath(
            '/SefinNacional/nfse',
            new Response($payload, ['Content-Type' => 'application/json'], 201)
        );

        $holder = new class () {
            public string $capturedXml = '';
        };

        $capturingSigner = new class ($holder) implements XmlSignerInterface {
            public function __construct(private object $holder)
            {
            }

            public function sign(string $xml, string $cnpj): string
            {
                $this->holder->capturedXml = $xml;

                return $xml;
            }
        };

        $client = $this->makeClient($capturingSigner);
        $client->emit($this->makeDps());

        self::assertNotSame('', $holder->capturedXml);

        $normalizedXml = str_replace(["\n", '  '], '', $holder->capturedXml);
        $tpAmbIndex    = strpos($normalizedXml, '<tpAmb>2</tpAmb>');
        $cLocEmiIndex  = strpos($normalizedXml, '<cLocEmi>3303302</cLocEmi>');

        self::assertNotFalse($tpAmbIndex);
        self::assertNotFalse($cLocEmiIndex);
        self::assertLessThan($cLocEmiIndex, $tpAmbIndex);
    }

    public function testQueryReturnsReceiptDataOnSuccess(): void
    {
        $payload = json_encode([
            'nNFSe'       => '99',
            'chaveAcesso' => 'xyz-456',
            'dhEmi'       => '2026-06-01T10:00:00',
        ], JSON_THROW_ON_ERROR);

        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/xyz-456',
            new Response($payload, ['Content-Type' => 'application/json'], 200)
        );

        $client = $this->makeClient($this->signer);

        $receipt = $client->query('xyz-456');

        self::assertSame('99', $receipt->nfseNumber);
    }

    public function testCancelReturnsTrueOnSuccess(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/abc-123/eventos',
            new Response('{}', ['Content-Type' => 'application/json'], 200)
        );

        $client = $this->makeClient($this->signer);

        self::assertTrue($client->cancel('abc-123', 'Cancelamento a pedido do tomador'));

        $request = self::$server->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getRequestMethod());
        self::assertSame('/SefinNacional/nfse/abc-123/eventos', $request->getRequestUri());

        $payload = json_decode($request->getInput(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertArrayHasKey('pedidoRegistroEventoXmlGZipB64', $payload);

        $compressedXml = base64_decode((string) $payload['pedidoRegistroEventoXmlGZipB64'], true);
        self::assertNotFalse($compressedXml);

        $eventoXml = gzdecode($compressedXml);
        self::assertNotFalse($eventoXml);
        self::assertStringContainsString('<pedRegEvento', $eventoXml);
        self::assertStringContainsString('<chNFSe>abc-123</chNFSe>', $eventoXml);
        self::assertStringContainsString('<e101101>', $eventoXml);
        self::assertStringContainsString('<xDesc>Cancelamento de NFS-e</xDesc>', $eventoXml);
        self::assertStringContainsString('<cMotivo>1</cMotivo>', $eventoXml);
        self::assertStringContainsString('<xMotivo>Cancelamento a pedido do tomador</xMotivo>', $eventoXml);
    }

    // -------------------------------------------------------------------------
    // Typed exception tests
    // -------------------------------------------------------------------------

    public function testEmitThrowsIssuanceExceptionWhenGatewayRejects(): void
    {
        $payload = json_encode(['codigo' => 'E422', 'mensagem' => 'CNPJ inválido'], JSON_THROW_ON_ERROR);

        self::$server->setResponseOfPath(
            '/SefinNacional/nfse',
            new Response($payload, ['Content-Type' => 'application/json'], 422),
        );

        $client = $this->makeClient($this->signer);

        $this->expectException(IssuanceException::class);
        $client->emit($this->makeDps());
    }

    public function testIssuanceExceptionCarriesErrorCodeHttpStatusAndUpstreamPayload(): void
    {
        $errorData = ['codigo' => 'E422', 'mensagem' => 'CNPJ inválido'];

        self::$server->setResponseOfPath(
            '/SefinNacional/nfse',
            new Response(json_encode($errorData, JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json'], 422),
        );

        $client = $this->makeClient($this->signer);

        try {
            $client->emit($this->makeDps());
            self::fail('Expected IssuanceException');
        } catch (IssuanceException $e) {
            self::assertSame(NfseErrorCode::IssuanceRejected, $e->errorCode);
            self::assertSame(422, $e->httpStatus);
            self::assertSame($errorData, $e->upstreamPayload);
        }
    }

    public function testQueryThrowsQueryExceptionWhenGatewayReturnsError(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/missing-key',
            new Response('{"error":"not found"}', ['Content-Type' => 'application/json'], 404),
        );

        $client = $this->makeClient($this->signer);

        $this->expectException(QueryException::class);
        $client->query('missing-key');
    }

    public function testQueryExceptionCarriesErrorCodeAndHttpStatus(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/missing-key',
            new Response('{"error":"not found"}', ['Content-Type' => 'application/json'], 404),
        );

        $client = $this->makeClient($this->signer);

        try {
            $client->query('missing-key');
            self::fail('Expected QueryException');
        } catch (QueryException $e) {
            self::assertSame(NfseErrorCode::QueryFailed, $e->errorCode);
            self::assertSame(404, $e->httpStatus);
        }
    }

    public function testCancelThrowsCancellationExceptionWhenGatewayReturnsError(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/blocked-key/eventos',
            new Response('{"error":"cannot cancel"}', ['Content-Type' => 'application/json'], 409),
        );

        $client = $this->makeClient($this->signer);

        $this->expectException(CancellationException::class);
        $client->cancel('blocked-key', 'a pedido do tomador');
    }

    public function testCancellationExceptionCarriesErrorCodeAndHttpStatus(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/blocked-key/eventos',
            new Response('{"error":"cannot cancel"}', ['Content-Type' => 'application/json'], 409),
        );

        $client = $this->makeClient($this->signer);

        try {
            $client->cancel('blocked-key', 'a pedido do tomador');
            self::fail('Expected CancellationException');
        } catch (CancellationException $e) {
            self::assertSame(NfseErrorCode::CancellationRejected, $e->errorCode);
            self::assertSame(409, $e->httpStatus);
        }
    }

    // -------------------------------------------------------------------------
    // getDanfse tests
    // -------------------------------------------------------------------------

    public function testGetDanfseGeneratesPdfLocallyFromXml(): void
    {
        $xml = file_get_contents(__DIR__ . '/../../fixtures/nfse_exemplo.xml');
        self::assertNotFalse($xml);

        $client = $this->makeClient($this->signer);

        $pdf = $client->getDanfse($xml);

        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function testGetDanfseThrowsArtifactExceptionForInvalidXml(): void
    {
        $client = $this->makeClient($this->signer);

        $this->expectException(ArtifactException::class);
        $client->getDanfse('not-valid-xml');
    }

    // -------------------------------------------------------------------------

    private function makeClient(?XmlSignerInterface $signer = null): NfseClient
    {
        return new NfseClient(
            environment: new EnvironmentConfig(
                baseUrl: self::$server->getServerRoot() . '/SefinNacional',
            ),
            cert:        new CertConfig(
                cnpj:      '29842527000145',
                pfxPath:   '/dev/null',
                vaultPath: 'secret/nfse/29842527000145',
                transportCertificatePath: '/tmp/client.crt.pem',
                transportPrivateKeyPath: '/tmp/client.key.pem',
            ),
            secretStore: new NoOpSecretStore(),
            signer:      $signer,
        );
    }

    private function makeDps(): DpsData
    {
        return new DpsData(
            cnpjPrestador:   '11222333000181',
            municipioIbge:   '3303302',
            itemListaServico: '0107',
            valorServico:    '1000.00',
            aliquota:        '5.00',
            discriminacao:   'Consultoria em TI',
        );
    }
}
