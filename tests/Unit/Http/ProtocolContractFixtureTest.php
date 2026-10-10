<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Http;

use LibreCodeCoop\NfsePHP\Config\AdnEnvironmentConfig;
use LibreCodeCoop\NfsePHP\Config\CertConfig;
use LibreCodeCoop\NfsePHP\Config\EnvironmentConfig;
use LibreCodeCoop\NfsePHP\Config\MunicipalParametersConfig;
use LibreCodeCoop\NfsePHP\Contracts\XmlSignerInterface;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\HttpResponseData;
use LibreCodeCoop\NfsePHP\Exception\GatewayException;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;
use LibreCodeCoop\NfsePHP\Http\AdnClient;
use LibreCodeCoop\NfsePHP\Http\MunicipalParametersClient;
use LibreCodeCoop\NfsePHP\Http\NfseClient;
use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Tests\Support\FakeHttpTransport;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

final class ProtocolContractFixtureTest extends TestCase
{
    public function testSefinIssuanceSuccessFixtureNormalizesReceiptAndCompressedXml(): void
    {
        $fixture = $this->fixture('sefin/issuance-success.json');
        $transport = $this->transport($fixture);

        $client = new NfseClient(
            environment: new EnvironmentConfig(
                sandboxMode: true,
                baseUrl: 'https://sefin.invalid.test/SefinNacional',
            ),
            cert: $this->certificate(),
            secretStore: new NoOpSecretStore(),
            signer: new class () implements XmlSignerInterface {
                public function sign(string $xml, string $cnpj): string
                {
                    return $xml;
                }
            },
            transport: $transport,
        );

        $receipt = $client->emit(new DpsData(
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0107',
            valorServico: '100.00',
            aliquota: '2.00',
            discriminacao: 'Synthetic contract fixture',
        ));

        self::assertSame('42', $receipt->nfseNumber);
        self::assertSame('ACCESS-42', $receipt->chaveAcesso);
        self::assertSame('2026-10-03T12:00:00-03:00', $receipt->dataEmissao);
        self::assertNotNull($receipt->rawXml);
        self::assertStringContainsString('<nNFSe>42</nNFSe>', $receipt->rawXml);
    }

    public function testSefinBusinessRejectionFixturePreservesUpstreamPayload(): void
    {
        $fixture = $this->fixture('sefin/business-rejection.json');
        $transport = $this->transport($fixture);

        $client = new NfseClient(
            environment: new EnvironmentConfig(baseUrl: 'https://sefin.invalid.test/SefinNacional'),
            cert: $this->certificate(),
            secretStore: new NoOpSecretStore(),
            transport: $transport,
        );

        try {
            $client->query('ACCESS-42');
            self::fail('Expected the synthetic SEFIN rejection fixture to throw.');
        } catch (GatewayException $exception) {
            self::assertSame(422, $exception->httpStatus);
            self::assertSame('E999', $exception->upstreamPayload['erros'][0]['Codigo'] ?? null);
        }
    }

    public function testDpsRecoveryFixtureReturnsAccessKeyWithoutNetwork(): void
    {
        $fixture = $this->fixture('sefin/dps-recovery-success.json');
        $transport = $this->transport($fixture);

        $client = $this->nfseClient($transport);

        self::assertSame('ACCESS-42', $client->queryDps('DPS112223330001810000000000000000000000000000'));
    }

    public function testCancellationFixtureUsesSignedEventAndAcceptsSuccessfulResponse(): void
    {
        $fixture = $this->fixture('sefin/cancellation-success.json');
        $transport = $this->transport($fixture);

        $client = $this->nfseClient($transport, $this->passthroughSigner());

        self::assertTrue($client->cancel('ACCESS-42', 'Synthetic cancellation test.'));
        self::assertCount(1, $transport->requests);
        self::assertSame('POST', $transport->requests[0]->method);
        self::assertStringContainsString('/nfse/ACCESS-42/eventos', $transport->requests[0]->url);
    }

    public function testEventQueryFixtureDecodesCompressedEventXml(): void
    {
        $fixture = $this->fixture('sefin/event-query-success.json');
        $transport = $this->transport($fixture);

        $event = $this->nfseClient($transport)->queryEvent('ACCESS-42', 101101);

        self::assertSame(2, $event->tipoAmbiente);
        self::assertSame('1.01', $event->versaoAplicativo);
        self::assertStringContainsString('<chNFSe>ACCESS-42</chNFSe>', $event->rawXml);
    }

    public function testMalformedCompressedXmlFixtureFailsAsInvalidUpstreamResponse(): void
    {
        $fixture = $this->fixture('sefin/malformed-compressed-xml.json');
        $transport = $this->transport($fixture);

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Invalid Base64');

        $this->nfseClient($transport)->query('ACCESS-42');
    }

    public function testMalformedJsonFixtureFailsAsInvalidUpstreamResponse(): void
    {
        $body = file_get_contents(
            dirname(__DIR__, 2) . '/fixtures/contracts/sefin/malformed-json.txt',
        );
        self::assertNotFalse($body);

        $transport = new FakeHttpTransport(new HttpResponseData(status: 200, body: $body));

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Unexpected non-JSON response');

        $this->nfseClient($transport)->query('ACCESS-42');
    }

    public function testInvalidGatewayResponseDoesNotExposeBodyInException(): void
    {
        $sensitiveMarker = 'SYNTHETIC_SECRET_DO_NOT_LOG';
        $body = '<html><p>' . $sensitiveMarker . '</p></html>';
        $transport = new FakeHttpTransport(new HttpResponseData(status: 200, body: $body));

        try {
            $this->nfseClient($transport)->query('ACCESS-42');
            self::fail('Expected unparseable gateway response to be rejected.');
        } catch (NetworkException $error) {
            self::assertSame(NfseErrorCode::InvalidResponse, $error->errorCode);
            self::assertSame('Unexpected non-JSON response from SEFIN gateway', $error->getMessage());
            self::assertStringNotContainsString($sensitiveMarker, $error->getMessage());
            self::assertStringNotContainsString('<html>', $error->getMessage());
        }
    }

    public function testAdnDistributionFixtureDecodesDistributedDocument(): void
    {
        $fixture = $this->fixture('adn/distribution-one-document.json');
        $transport = $this->transport($fixture);

        $client = new AdnClient(
            environment: new AdnEnvironmentConfig(
                sandboxMode: true,
                baseUrl: 'https://adn.invalid.test/contribuintes',
            ),
            cert: $this->certificate(),
            transport: $transport,
        );

        $distribution = $client->getDfe(10);

        self::assertSame('PROCESSADO', $distribution->statusProcessamento);
        self::assertSame(11, $distribution->ultimoNsu);
        self::assertCount(1, $distribution->documents);
        self::assertSame('ACCESS-42', $distribution->documents[0]->chaveAcesso);
        self::assertNotNull($distribution->documents[0]->xml);
        self::assertStringContainsString('<nNFSe>42</nNFSe>', $distribution->documents[0]->xml);
    }

    public function testMunicipalParametersFixtureReturnsVersionedPayloadShape(): void
    {
        $fixture = $this->fixture('municipal/convenio-success.json');
        $transport = $this->transport($fixture);

        $client = new MunicipalParametersClient(
            config: new MunicipalParametersConfig(
                sandboxMode: true,
                baseUrl: 'https://adn.invalid.test/parametrizacao',
            ),
            cert: $this->certificate(),
            transport: $transport,
        );

        self::assertSame([
            'conveniado' => true,
            'codigoMunicipio' => '3303302',
        ], $client->convenio('3303302'));
    }

    public function testMunicipalUtf8FixturePreservesAccentedProtocolText(): void
    {
        $fixture = $this->fixture('municipal/utf8-benefit-success.json');
        $transport = $this->transport($fixture);

        $client = new MunicipalParametersClient(
            config: new MunicipalParametersConfig(
                sandboxMode: true,
                baseUrl: 'https://adn.invalid.test/parametrizacao',
            ),
            cert: $this->certificate(),
            transport: $transport,
        );

        $result = $client->beneficio('3303302', 'BEN-1', '2026-10-03');

        self::assertSame('Redução de base em São Gonçalo', $result['descricao'] ?? null);
        self::assertSame('Serviço técnico – homologação', $result['observacao'] ?? null);
    }

    /**
     * @dataProvider fixtureProvider
     */
    public function testEveryContractFixtureDeclaresOriginAndContract(string $path): void
    {
        $fixture = $this->fixture($path);

        self::assertContains($fixture['_meta']['kind'] ?? null, ['synthetic', 'sanitized']);
        self::assertNotSame('', trim((string) ($fixture['_meta']['contract'] ?? '')));
        self::assertNotSame('', trim((string) ($fixture['_meta']['scenario'] ?? '')));
        self::assertIsInt($fixture['status'] ?? null);
        self::assertIsArray($fixture['response'] ?? null);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fixtureProvider(): array
    {
        return [
            'SEFIN issuance success' => ['sefin/issuance-success.json'],
            'SEFIN business rejection' => ['sefin/business-rejection.json'],
            'SEFIN DPS recovery' => ['sefin/dps-recovery-success.json'],
            'SEFIN cancellation' => ['sefin/cancellation-success.json'],
            'SEFIN event query' => ['sefin/event-query-success.json'],
            'SEFIN malformed compressed XML' => ['sefin/malformed-compressed-xml.json'],
            'ADN distribution' => ['adn/distribution-one-document.json'],
            'municipal agreement' => ['municipal/convenio-success.json'],
            'municipal UTF-8 benefit' => ['municipal/utf8-benefit-success.json'],
        ];
    }

    /**
     * @return array{
     *   _meta: array{kind:string,contract:string,scenario:string},
     *   status:int,
     *   response:array<string,mixed>
     * }
     */
    private function fixture(string $relativePath): array
    {
        $path = dirname(__DIR__, 2) . '/fixtures/contracts/' . $relativePath;
        $contents = file_get_contents($path);

        self::assertNotFalse($contents);

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array{status:int,response:array<string,mixed>} $fixture
     */
    private function transport(array $fixture): FakeHttpTransport
    {
        return new FakeHttpTransport(new HttpResponseData(
            status: $fixture['status'],
            body: json_encode($fixture['response'], JSON_THROW_ON_ERROR),
        ));
    }

    private function nfseClient(
        FakeHttpTransport $transport,
        ?XmlSignerInterface $signer = null,
    ): NfseClient {
        return new NfseClient(
            environment: new EnvironmentConfig(
                sandboxMode: true,
                baseUrl: 'https://sefin.invalid.test/SefinNacional',
            ),
            cert: $this->certificate(),
            secretStore: new NoOpSecretStore(),
            signer: $signer,
            transport: $transport,
        );
    }

    private function passthroughSigner(): XmlSignerInterface
    {
        return new class () implements XmlSignerInterface {
            public function sign(string $xml, string $cnpj): string
            {
                return $xml;
            }
        };
    }

    private function certificate(): CertConfig
    {
        return new CertConfig(
            cnpj: '11222333000181',
            pfxPath: '/does/not/exist.p12',
            vaultPath: 'test/fixture-only',
        );
    }
}
