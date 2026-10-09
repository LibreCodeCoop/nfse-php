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
use LibreCodeCoop\NfsePHP\Exception\EventRegistrationException;
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


    public function testEmitReadsNfseNumberFromDocumentedGatewayXmlResponse(): void
    {
        $authorizedXml = file_get_contents(__DIR__ . '/../../fixtures/nfse_exemplo.xml');
        self::assertIsString($authorizedXml);
        $payload = json_encode([
            'tipoAmbiente' => 2,
            'versaoAplicativo' => 'SefinNacional_1.6.0',
            'dataHoraProcessamento' => '2026-10-09T09:30:00-03:00',
            'idDps' => 'DPS-EXAMPLE',
            'chaveAcesso' => str_repeat('9', 50),
            'nfseXmlGZipB64' => base64_encode(gzencode($authorizedXml)),
        ], JSON_THROW_ON_ERROR);
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse',
            new Response($payload, ['Content-Type' => 'application/json'], 201)
        );

        $receipt = $this->makeClient($this->signer)->emit($this->makeDps());

        self::assertSame('10', $receipt->nfseNumber);
        self::assertSame(str_repeat('9', 50), $receipt->chaveAcesso);
        self::assertSame($authorizedXml, $receipt->rawXml);
        self::assertSame('2026-10-09T09:30:00-03:00', $receipt->dataEmissao);
    }

    public function testQueryReadsNfseNumberFromAuthorizedXmlWhenJsonDoesNotContainIt(): void
    {
        $authorizedXml = file_get_contents(__DIR__ . '/../../fixtures/nfse_exemplo.xml');
        self::assertIsString($authorizedXml);
        $payload = json_encode([
            'chaveAcesso' => 'existing-nfse',
            'nfseXmlGZipB64' => base64_encode(gzencode($authorizedXml)),
            'dataHoraProcessamento' => '2026-10-09T09:31:00-03:00',
        ], JSON_THROW_ON_ERROR);
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/existing-nfse',
            new Response($payload, ['Content-Type' => 'application/json'], 200)
        );

        $receipt = $this->makeClient($this->signer)->query('existing-nfse');

        self::assertSame('10', $receipt->nfseNumber);
        self::assertSame('existing-nfse', $receipt->chaveAcesso);
        self::assertSame($authorizedXml, $receipt->rawXml);
    }

    public function testEmitRejectsSuccessStatusWithoutAuthorizedFiscalIdentity(): void
    {
        $payload = json_encode([
            'tipoAmbiente' => 2,
            'dataHoraProcessamento' => '2026-10-09T09:32:00-03:00',
            'erros' => [['codigo' => 'E0312', 'descricao' => 'Operacao nao autorizada']],
        ], JSON_THROW_ON_ERROR);
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse',
            new Response($payload, ['Content-Type' => 'application/json'], 200)
        );

        try {
            $this->makeClient($this->signer)->emit($this->makeDps());
            self::fail('An incomplete 2xx response must not become an authorized receipt.');
        } catch (\LibreCodeCoop\NfsePHP\Exception\NetworkException $error) {
            self::assertSame(NfseErrorCode::InvalidResponse, $error->errorCode);
            self::assertStringContainsString('chaveAcesso', $error->getMessage());
            self::assertStringContainsString('Recover using the DPS identifier', $error->getMessage());
        }
    }

    public function testEmitDoesNotAcceptKeyWithoutInvoiceNumberOrDocument(): void
    {
        $payload = json_encode(['chaveAcesso' => str_repeat('1', 50)], JSON_THROW_ON_ERROR);
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse',
            new Response($payload, ['Content-Type' => 'application/json'], 201)
        );

        $this->expectException(\LibreCodeCoop\NfsePHP\Exception\NetworkException::class);
        $this->expectExceptionMessage('missing nNFSe');
        $this->makeClient($this->signer)->emit($this->makeDps());
    }

    public function testEmitDoesNotParseNumberFromAnUnrelatedXmlElement(): void
    {
        $unrelated = '<untrusted><nNFSe>999</nNFSe></untrusted>';
        $payload = json_encode([
            'chaveAcesso' => str_repeat('1', 50),
            'nfseXmlGZipB64' => base64_encode(gzencode($unrelated)),
        ], JSON_THROW_ON_ERROR);
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse',
            new Response($payload, ['Content-Type' => 'application/json'], 201)
        );

        $this->expectException(\LibreCodeCoop\NfsePHP\Exception\NetworkException::class);
        $this->expectExceptionMessage('missing nNFSe');
        $this->makeClient($this->signer)->emit($this->makeDps());
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

    public function testQueryDpsReturnsAccessKeyAndAcceptsInfDpsPrefix(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/dps/330330221122233300018100001000000000000001',
            new Response('{"chaveAcesso":"12345678901234567890123456789012345678901234567890"}', ['Content-Type' => 'application/json'], 200)
        );

        $client = $this->makeClient($this->signer);

        self::assertSame(
            '12345678901234567890123456789012345678901234567890',
            $client->queryDps('DPS330330221122233300018100001000000000000001'),
        );

        $request = self::$server->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('GET', $request->getRequestMethod());
    }

    public function testExistsDpsUsesHeadAndReturnsTrueWhenFound(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/dps/330330221122233300018100001000000000000001',
            new Response('', [], 200)
        );

        $client = $this->makeClient($this->signer);

        self::assertTrue($client->existsDps('330330221122233300018100001000000000000001'));

        $request = self::$server->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('HEAD', $request->getRequestMethod());
    }

    public function testExistsDpsReturnsFalseOnNotFound(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/dps/missing-dps',
            new Response('', [], 404)
        );

        $client = $this->makeClient($this->signer);

        self::assertFalse($client->existsDps('missing-dps'));
    }

    public function testQueryDpsRejectsMissingAccessKey(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/dps/known-without-key',
            new Response('{}', ['Content-Type' => 'application/json'], 200)
        );

        $client = $this->makeClient($this->signer);

        $this->expectException(\LibreCodeCoop\NfsePHP\Exception\NetworkException::class);
        $client->queryDps('known-without-key');
    }

    public function testQueryEventReturnsDecodedEventXml(): void
    {
        $eventXml = '<evento versao="1.01"><infEvento Id="EVT123"/></evento>';
        $payload = json_encode([
            'tipoAmbiente' => 2,
            'versaoAplicativo' => 'Sefin_1.0',
            'dataHoraProcessamento' => '2026-10-03T04:00:00-03:00',
            'eventoXmlGZipB64' => base64_encode(gzencode($eventXml)),
        ], JSON_THROW_ON_ERROR);

        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/ACCESS-KEY/eventos/101101/1',
            new Response($payload, ['Content-Type' => 'application/json'], 200)
        );

        $client = $this->makeClient($this->signer);
        $event = $client->queryEvent('ACCESS-KEY', 101101, 1);

        self::assertSame(2, $event->tipoAmbiente);
        self::assertSame('Sefin_1.0', $event->versaoAplicativo);
        self::assertSame('2026-10-03T04:00:00-03:00', $event->dataHoraProcessamento);
        self::assertSame($eventXml, $event->rawXml);

        $request = self::$server->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('GET', $request->getRequestMethod());
    }

    public function testQueryEventRejectsInvalidEventTypeBeforeRequest(): void
    {
        $client = $this->makeClient($this->signer);

        $this->expectException(\InvalidArgumentException::class);
        $client->queryEvent('ACCESS-KEY', 12345);
    }

    public function testQueryEventRejectsInvalidSequenceBeforeRequest(): void
    {
        $client = $this->makeClient($this->signer);

        $this->expectException(\InvalidArgumentException::class);
        $client->queryEvent('ACCESS-KEY', 101101, 0);
    }

    public function testQueryEventThrowsQueryExceptionWhenNotFound(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/ACCESS-KEY/eventos/101101/1',
            new Response('{"error":"not found"}', ['Content-Type' => 'application/json'], 404)
        );

        $client = $this->makeClient($this->signer);

        $this->expectException(QueryException::class);
        $client->queryEvent('ACCESS-KEY', 101101, 1);
    }

    public function testQueryEventRejectsMalformedCompressedXml(): void
    {
        $payload = json_encode([
            'tipoAmbiente' => 2,
            'versaoAplicativo' => 'Sefin_1.0',
            'dataHoraProcessamento' => '2026-10-03T04:00:00-03:00',
            'eventoXmlGZipB64' => base64_encode('not-gzip'),
        ], JSON_THROW_ON_ERROR);

        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/ACCESS-KEY/eventos/101101/1',
            new Response($payload, ['Content-Type' => 'application/json'], 200)
        );

        $client = $this->makeClient($this->signer);

        $this->expectException(\LibreCodeCoop\NfsePHP\Exception\NetworkException::class);
        $client->queryEvent('ACCESS-KEY', 101101, 1);
    }

    public function testRegisterEventXmlSignsAndPostsThroughDocumentedEndpoint(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/abc-123/eventos',
            new Response('{"sucesso":true}', ['Content-Type' => 'application/json'], 200),
        );

        $client = $this->makeClient($this->signer);
        $result = $client->registerEventXml(
            'abc-123',
            '<pedRegEvento><infPedReg><chNFSe>abc-123</chNFSe><e105102><xDesc>Evento sintético</xDesc></e105102></infPedReg></pedRegEvento>',
        );

        self::assertTrue($result->accepted);
        self::assertSame(200, $result->httpStatus);
        self::assertSame(['sucesso' => true], $result->response);

        $request = self::$server->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getRequestMethod());
        self::assertSame('/SefinNacional/nfse/abc-123/eventos', $request->getRequestUri());

        $payload = json_decode($request->getInput(), true, 512, JSON_THROW_ON_ERROR);
        $compressed = base64_decode((string) $payload['pedidoRegistroEventoXmlGZipB64'], true);
        self::assertNotFalse($compressed);
        $xml = gzdecode($compressed);
        self::assertNotFalse($xml);
        self::assertStringContainsString('<e105102>', $xml);
    }

    public function testRegisterEventXmlRejectsMismatchedAccessKeyBeforeNetwork(): void
    {
        $client = $this->makeClient($this->signer);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('access key does not match');

        $client->registerEventXml(
            'abc-123',
            '<pedRegEvento><infPedReg><chNFSe>different</chNFSe><e105102/></infPedReg></pedRegEvento>',
        );
    }

    public function testRegisterEventXmlExposesTypedGatewayRejection(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/blocked/eventos',
            new Response('{"codigo":"EVT001"}', ['Content-Type' => 'application/json'], 422),
        );

        $client = $this->makeClient($this->signer);

        try {
            $client->registerEventXml(
                'blocked',
                '<pedRegEvento><infPedReg><chNFSe>blocked</chNFSe><e105102/></infPedReg></pedRegEvento>',
            );
            self::fail('Expected EventRegistrationException');
        } catch (EventRegistrationException $e) {
            self::assertSame(NfseErrorCode::EventRegistrationRejected, $e->errorCode);
            self::assertSame(422, $e->httpStatus);
            self::assertSame(['codigo' => 'EVT001'], $e->upstreamPayload);
        }
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

    public function testCancelWithReasonUsesRequestedOfficialReasonCode(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/abc-123/eventos',
            new Response('{}', ['Content-Type' => 'application/json'], 200)
        );

        $client = $this->makeClient($this->signer);
        self::assertTrue($client->cancelWithReason('abc-123', '9', 'Outro motivo válido'));

        $request = self::$server->getLastRequest();
        self::assertNotNull($request);
        $payload = json_decode($request->getInput(), true, 512, JSON_THROW_ON_ERROR);
        $compressedXml = base64_decode((string) $payload['pedidoRegistroEventoXmlGZipB64'], true);
        self::assertNotFalse($compressedXml);
        $eventoXml = gzdecode($compressedXml);
        self::assertNotFalse($eventoXml);
        self::assertStringContainsString('<cMotivo>9</cMotivo>', $eventoXml);
        self::assertStringContainsString('<xMotivo>Outro motivo válido</xMotivo>', $eventoXml);
    }

    public function testCancelWithReasonAcceptsOfficialDescriptionBoundaries(): void
    {
        self::$server->setResponseOfPath(
            '/SefinNacional/nfse/abc-123/eventos',
            new Response('{}', ['Content-Type' => 'application/json'], 200)
        );

        $client = $this->makeClient($this->signer);
        self::assertTrue($client->cancelWithReason('abc-123', '1', str_repeat('a', 15)));
        self::assertTrue($client->cancelWithReason('abc-123', '2', str_repeat('b', 255)));
    }

    /**
     * @dataProvider invalidCancellationReasons
     */
    public function testCancelWithReasonRejectsInvalidContractBeforeNetwork(string $code, string $description): void
    {
        $client = $this->makeClient($this->signer);

        $this->expectException(\InvalidArgumentException::class);
        $client->cancelWithReason('abc-123', $code, $description);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidCancellationReasons(): array
    {
        return [
            'unknown reason code' => ['3', str_repeat('a', 15)],
            'description too short' => ['1', str_repeat('a', 14)],
            'description too long' => ['1', str_repeat('a', 256)],
        ];
    }

    public function testEmitRejectsMalformedBase64NfseXmlWithoutPhpWarning(): void
    {
        $payload = json_encode([
            'nNFSe' => '42',
            'chaveAcesso' => 'abc-123',
            'nfseXmlGZipB64' => '***not-base64***',
        ], JSON_THROW_ON_ERROR);

        self::$server->setResponseOfPath(
            '/SefinNacional/nfse',
            new Response($payload, ['Content-Type' => 'application/json'], 201)
        );

        $client = $this->makeClient($this->signer);

        try {
            $client->emit($this->makeDps());
            self::fail('Expected NetworkException');
        } catch (\LibreCodeCoop\NfsePHP\Exception\NetworkException $e) {
            self::assertSame(NfseErrorCode::InvalidResponse, $e->errorCode);
            self::assertStringContainsString('Invalid Base64', $e->getMessage());
        }
    }

    public function testEmitRejectsMalformedGzipNfseXmlWithoutPhpWarning(): void
    {
        $payload = json_encode([
            'nNFSe' => '42',
            'chaveAcesso' => 'abc-123',
            'nfseXmlGZipB64' => base64_encode('not-gzip'),
        ], JSON_THROW_ON_ERROR);

        self::$server->setResponseOfPath(
            '/SefinNacional/nfse',
            new Response($payload, ['Content-Type' => 'application/json'], 201)
        );

        $client = $this->makeClient($this->signer);

        try {
            $client->emit($this->makeDps());
            self::fail('Expected NetworkException');
        } catch (\LibreCodeCoop\NfsePHP\Exception\NetworkException $e) {
            self::assertSame(NfseErrorCode::InvalidResponse, $e->errorCode);
            self::assertStringContainsString('Invalid GZip', $e->getMessage());
        }
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
