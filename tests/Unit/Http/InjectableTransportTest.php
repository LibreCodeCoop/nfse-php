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
use LibreCodeCoop\NfsePHP\Dto\DecisionIssuerAddressData;
use LibreCodeCoop\NfsePHP\Dto\DecisionNfseData;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\HttpResponseData;
use LibreCodeCoop\NfsePHP\Dto\SubstitutionData;
use LibreCodeCoop\NfsePHP\Http\AdnClient;
use LibreCodeCoop\NfsePHP\Http\MunicipalParametersClient;
use LibreCodeCoop\NfsePHP\Http\NfseClient;
use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Tests\Support\FakeHttpTransport;
use LibreCodeCoop\NfsePHP\Tests\TestCase;

final class InjectableTransportTest extends TestCase
{
    public function testSefinIssuanceUsesFakeTransportWithoutCertificateFilesOrNetwork(): void
    {
        $transport = new FakeHttpTransport(new HttpResponseData(
            201,
            json_encode([
                'nNFSe' => '42',
                'chaveAcesso' => 'ACCESS-42',
                'dataHoraProcessamento' => '2026-10-03T12:00:00-03:00',
            ], JSON_THROW_ON_ERROR),
        ));

        $signer = new class () implements XmlSignerInterface {
            public function sign(string $xml, string $cnpj): string
            {
                return $xml;
            }
        };

        $client = new NfseClient(
            environment: new EnvironmentConfig(
                sandboxMode: true,
                baseUrl: 'https://sefin.invalid.test/SefinNacional',
            ),
            cert: new CertConfig(
                cnpj: '11222333000181',
                pfxPath: '/does/not/exist.p12',
                vaultPath: 'test/no-secret-required',
            ),
            secretStore: new NoOpSecretStore(),
            signer: $signer,
            transport: $transport,
        );

        $receipt = $client->emit(new DpsData(
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0107',
            valorServico: '100.00',
            aliquota: '2.00',
            discriminacao: 'Teste deterministico',
        ));

        self::assertSame('42', $receipt->nfseNumber);
        self::assertCount(1, $transport->requests);

        $request = $transport->requests[0];
        self::assertSame('POST', $request->method);
        self::assertSame('https://sefin.invalid.test/SefinNacional/nfse', $request->url);
        self::assertSame('application/json', $request->headers['Content-Type']);
        self::assertNull($request->clientCertificatePath);
        self::assertNull($request->clientPrivateKeyPath);
        self::assertNotNull($request->body);

        $payload = json_decode($request->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('dpsXmlGZipB64', $payload);
    }

    public function testDecisionIssuanceUsesDedicatedBypassEndpointAndPayloadContract(): void
    {
        $transport = new FakeHttpTransport(new HttpResponseData(
            201,
            '{"nNFSe":"240","chaveAcesso":"DECISION-240","dataHoraProcessamento":"2026-10-04T12:00:00-03:00"}',
        ));

        $client = new NfseClient(
            environment: new EnvironmentConfig(
                sandboxMode: true,
                baseUrl: 'https://sefin.invalid.test/SefinNacional',
            ),
            cert: new CertConfig(
                cnpj: '11222333000181',
                pfxPath: '/does/not/exist.p12',
                vaultPath: 'test/no-secret-required',
            ),
            secretStore: new NoOpSecretStore(),
            signer: new class () implements XmlSignerInterface {
                public function sign(string $xml, string $cnpj): string
                {
                    return $xml;
                }
            },
            transport: $transport,
        );

        $receipt = $client->emitDecision(new DecisionNfseData(
            dps: new DpsData(
                cnpjPrestador: '11222333000181',
                municipioIbge: '3303302',
                itemListaServico: '001',
                valorServico: '1000.00',
                aliquota: '5.00',
                discriminacao: 'Consultoria em tecnologia da informacao',
                tipoAmbiente: 2,
                serie: '1',
                numeroDps: '42',
                dataCompetencia: '2026-10-04',
                codigoTributacaoNacional: '010701',
                opcaoSimplesNacional: 1,
                regimeEspecialTributacao: 0,
                tipoRetencaoIss: 1,
                indicadorTributacao: 0,
            ),
            numeroNfse: '240',
            codigoNumerico: '123456789',
            localEmissao: 'Niteroi',
            localPrestacao: 'Niteroi',
            descricaoTributacaoNacional: 'Consultoria em tecnologia da informacao',
            emitenteNome: 'Prestador de Teste Ltda',
            emitenteEndereco: new DecisionIssuerAddressData(
                'Rua de Teste',
                '100',
                'Centro',
                '3303302',
                'RJ',
                '24000000',
            ),
            valorLiquido: '1000.00',
            numeroDfse: '1',
            codigoLocalIncidencia: '3303302',
            localIncidencia: 'Niteroi',
            baseCalculo: '1000.00',
            aliquotaAplicada: '5.00',
            valorIssqn: '50.00',
        ));

        self::assertSame('240', $receipt->nfseNumber);
        self::assertCount(1, $transport->requests);

        $request = $transport->requests[0];
        self::assertSame('POST', $request->method);
        self::assertSame(
            'https://sefin.invalid.test/SefinNacional/decisao-judicial/nfse',
            $request->url,
        );
        self::assertNotNull($request->body);

        $payload = json_decode($request->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['xmlGZipB64'], array_keys($payload));

        $compressed = base64_decode((string) $payload['xmlGZipB64'], true);
        self::assertNotFalse($compressed);
        $xml = gzdecode($compressed);
        self::assertNotFalse($xml);
        self::assertStringContainsString('<NFSe', $xml);
        self::assertStringContainsString('<cStat>102</cStat>', $xml);
    }

    public function testSubstitutionUsesNormalIssuanceEndpointWithoutExtraMutationRequest(): void
    {
        $transport = new FakeHttpTransport(new HttpResponseData(
            201,
            '{"nNFSe":"43","chaveAcesso":"REPLACEMENT-43","dataHoraProcessamento":"2026-10-04T10:00:00-03:00"}',
        ));

        $client = new NfseClient(
            environment: new EnvironmentConfig(
                sandboxMode: true,
                baseUrl: 'https://sefin.invalid.test/SefinNacional',
            ),
            cert: new CertConfig(
                cnpj: '11222333000181',
                pfxPath: '/does/not/exist.p12',
                vaultPath: 'test/no-secret-required',
            ),
            secretStore: new NoOpSecretStore(),
            signer: new class () implements XmlSignerInterface {
                public function sign(string $xml, string $cnpj): string
                {
                    return $xml;
                }
            },
            transport: $transport,
        );

        $client->emit(new DpsData(
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '0107',
            valorServico: '100.00',
            aliquota: '2.00',
            discriminacao: 'Substituicao deterministica',
            substituicao: new SubstitutionData(
                chaveNfseSubstituida: str_repeat('1', 50),
                codigoMotivo: '01',
            ),
        ));

        self::assertCount(1, $transport->requests);
        $request = $transport->requests[0];
        self::assertSame('POST', $request->method);
        self::assertSame('https://sefin.invalid.test/SefinNacional/nfse', $request->url);

        $payload = json_decode((string) $request->body, true, 512, JSON_THROW_ON_ERROR);
        $compressed = base64_decode((string) ($payload['dpsXmlGZipB64'] ?? ''), true);
        self::assertNotFalse($compressed);
        $xml = gzdecode($compressed);
        self::assertNotFalse($xml);
        self::assertStringContainsString('<subst>', $xml);
        self::assertStringContainsString('<chSubstda>' . str_repeat('1', 50) . '</chSubstda>', $xml);
        self::assertStringContainsString('<cMotivo>01</cMotivo>', $xml);
    }

    public function testAdnDistributionUsesFakeTransportWithoutCertificateFiles(): void
    {
        $transport = new FakeHttpTransport(new HttpResponseData(
            200,
            json_encode([
                'StatusProcessamento' => 'PROCESSADO',
                'TipoAmbiente' => '2',
                'DataHoraProcessamento' => '2026-10-03T12:00:00-03:00',
                'LoteDFe' => [],
            ], JSON_THROW_ON_ERROR),
        ));

        $client = new AdnClient(
            environment: new AdnEnvironmentConfig(
                sandboxMode: true,
                baseUrl: 'https://adn.invalid.test/contribuintes',
            ),
            cert: new CertConfig(
                cnpj: '11222333000181',
                pfxPath: '/does/not/exist.p12',
                vaultPath: 'test/no-secret-required',
            ),
            transport: $transport,
        );

        $result = $client->getDfe(10);

        self::assertSame('PROCESSADO', $result->statusProcessamento);
        self::assertCount(1, $transport->requests);
        self::assertSame('GET', $transport->requests[0]->method);
        self::assertSame(
            'https://adn.invalid.test/contribuintes/DFe/10?lote=true',
            $transport->requests[0]->url,
        );
        self::assertNull($transport->requests[0]->clientCertificatePath);
    }

    public function testMunicipalParametersUseFakeTransportWithoutCertificateFiles(): void
    {
        $transport = new FakeHttpTransport(new HttpResponseData(
            200,
            '{"conveniado":true}',
        ));

        $client = new MunicipalParametersClient(
            config: new MunicipalParametersConfig(
                sandboxMode: true,
                baseUrl: 'https://adn.invalid.test/parametrizacao',
            ),
            cert: new CertConfig(
                cnpj: '11222333000181',
                pfxPath: '/does/not/exist.p12',
                vaultPath: 'test/no-secret-required',
            ),
            transport: $transport,
        );

        self::assertSame(['conveniado' => true], $client->convenio('3303302'));
        self::assertCount(1, $transport->requests);
        self::assertSame(
            'https://adn.invalid.test/parametrizacao/3303302/convenio',
            $transport->requests[0]->url,
        );
        self::assertNull($transport->requests[0]->clientCertificatePath);
    }

    public function testClientsForwardPreparedMtlsPathsToInjectedTransport(): void
    {
        $transport = new FakeHttpTransport(new HttpResponseData(
            200,
            '{"nNFSe":"1","chaveAcesso":"KEY","dhEmi":"2026-10-03"}',
        ));

        $client = new NfseClient(
            environment: new EnvironmentConfig(baseUrl: 'https://sefin.invalid.test'),
            cert: new CertConfig(
                cnpj: '11222333000181',
                pfxPath: '/source.p12',
                vaultPath: 'pfx/test',
                transportCertificatePath: '/tmp/client-cert.pem',
                transportPrivateKeyPath: '/tmp/client-key.pem',
            ),
            secretStore: new NoOpSecretStore(),
            signer: new class () implements XmlSignerInterface {
                public function sign(string $xml, string $cnpj): string
                {
                    return $xml;
                }
            },
            transport: $transport,
        );

        $client->query('KEY');

        self::assertSame('/tmp/client-cert.pem', $transport->requests[0]->clientCertificatePath);
        self::assertSame('/tmp/client-key.pem', $transport->requests[0]->clientPrivateKeyPath);
    }
}
