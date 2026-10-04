<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Xml;

use LibreCodeCoop\NfsePHP\Dto\DecisionIssuerAddressData;
use LibreCodeCoop\NfsePHP\Dto\DecisionNfseData;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
use LibreCodeCoop\NfsePHP\Dto\SubstitutionData;
use LibreCodeCoop\NfsePHP\SecretStore\NoOpSecretStore;
use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Xml\DecisionNfseBuilder;
use LibreCodeCoop\NfsePHP\Xml\DpsSigner;
use LibreCodeCoop\NfsePHP\Xml\NfseSchemaValidator;

final class DecisionNfseBuilderTest extends TestCase
{
    private string $pfxPath = '';

    protected function tearDown(): void
    {
        if ($this->pfxPath !== '' && is_file($this->pfxPath)) {
            unlink($this->pfxPath);
        }
        parent::tearDown();
    }

    public function testBuildsOfficialDecisionFlowFixedFields(): void
    {
        $xml = (new DecisionNfseBuilder())->build($this->decision());
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');

        self::assertSame('102', $xpath->evaluate('string(/n:NFSe/n:infNFSe/n:cStat)'));
        self::assertSame('2', $xpath->evaluate('string(/n:NFSe/n:infNFSe/n:ambGer)'));
        self::assertSame('1', $xpath->evaluate('string(/n:NFSe/n:infNFSe/n:tpEmis)'));
        self::assertSame('0', $xpath->evaluate('string(/n:NFSe/n:infNFSe/n:nDFSe)'));
        self::assertSame(
            $xpath->evaluate('string(/n:NFSe/n:infNFSe/n:DPS/n:infDPS/n:dhEmi)'),
            $xpath->evaluate('string(/n:NFSe/n:infNFSe/n:dhProc)'),
        );
        $id = $xpath->evaluate('string(/n:NFSe/n:infNFSe/@Id)');
        self::assertMatchesRegularExpression('/^NFS\d{50}$/', $id);
    }

    public function testSignedSchemaCompatibleRepresentativePassesNfseSchema(): void
    {
        $data = $this->decision('1');
        $signed = $this->signer()->sign((new DecisionNfseBuilder())->build($data), $data->dps->cnpjPrestador);
        self::assertSame([], (new NfseSchemaValidator())->validate($signed));
    }

    public function testManualNdfseZeroMakesSchemaConflictExplicit(): void
    {
        $data = $this->decision();
        $signed = $this->signer()->sign((new DecisionNfseBuilder())->build($data), $data->dps->cnpjPrestador);
        $errors = (new NfseSchemaValidator())->validate($signed);
        self::assertTrue((bool) array_filter($errors, static fn (string $e): bool => str_contains($e, 'nDFSe')));
    }

    public function testCompleteNfseValidatorRejectsUnsupportedSchemaVersionExplicitly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported complete NFS-e schema version: 9.99');

        new NfseSchemaValidator(schemaVersion: '9.99');
    }

    public function testCompleteNfseValidatorDoesNotResolveExternalEntities(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE NFSe SYSTEM "https://example.invalid/nfse.dtd"><NFSe/>';

        self::assertNotSame([], (new NfseSchemaValidator())->validate($xml));
    }

    public function testKnownAccessKeyCheckDigit(): void
    {
        $key = '35503081206169966000133000000000023726079719343785';
        self::assertSame(5, DecisionNfseBuilder::accessKeyCheckDigit(substr($key, 0, 49)));
    }

    public function testDecisionFlowPreservesOfficialSubstitutionRelationship(): void
    {
        $dps = $this->dps(substitution: new SubstitutionData(
            chaveNfseSubstituida: str_repeat('1', 50),
            codigoMotivo: '01',
        ));

        $xml = (new DecisionNfseBuilder())->build($this->decision(dps: $dps));

        self::assertStringContainsString('<subst>', $xml);
        self::assertStringContainsString(
            '<chSubstda>' . str_repeat('1', 50) . '</chSubstda>',
            $xml,
        );
        self::assertStringContainsString('<cMotivo>01</cMotivo>', $xml);
    }

    public function testIbsCbsIsRejectedUntilCompleteNfseCalculatedValuesAreModeled(): void
    {
        $dps = $this->dps(ibs: true);
        $this->expectException(\LogicException::class);
        (new DecisionNfseBuilder())->build($this->decision(dps: $dps));
    }

    private function decision(string $numeroDfse = '0', ?DpsData $dps = null): DecisionNfseData
    {
        return new DecisionNfseData(
            dps: $dps ?? $this->dps(),
            numeroNfse: '240',
            codigoNumerico: '123456789',
            localEmissao: 'Niterói',
            localPrestacao: 'Niterói',
            descricaoTributacaoNacional: 'Consultoria em tecnologia da informação',
            emitenteNome: 'Prestador de Teste Ltda',
            emitenteEndereco: new DecisionIssuerAddressData('Rua de Teste', '100', 'Centro', '3303302', 'RJ', '24000000'),
            valorLiquido: '1000.00',
            numeroDfse: $numeroDfse,
            codigoLocalIncidencia: '3303302',
            localIncidencia: 'Niterói',
            emitenteInscricaoMunicipal: '123456',
            baseCalculo: '1000.00',
            aliquotaAplicada: '5.00',
            valorIssqn: '50.00',
        );
    }

    private function dps(bool $ibs = false, ?SubstitutionData $substitution = null): DpsData
    {
        return new DpsData(
            cnpjPrestador: '11222333000181',
            municipioIbge: '3303302',
            itemListaServico: '001',
            valorServico: '1000.00',
            aliquota: '5.00',
            discriminacao: 'Consultoria em tecnologia da informacao',
            tipoAmbiente: 2,
            serie: '1',
            numeroDps: '42',
            dataCompetencia: '2026-10-03',
            codigoTributacaoNacional: '010701',
            documentoTomador: '12345678000195',
            nomeTomador: 'Cliente de Teste',
            tomadorCodigoMunicipio: '3304557',
            tomadorCep: '20040020',
            tomadorLogradouro: 'Rua de Teste',
            tomadorNumero: '100',
            tomadorBairro: 'Centro',
            opcaoSimplesNacional: 1,
            regimeEspecialTributacao: 0,
            tipoRetencaoIss: 1,
            indicadorTributacao: 0,
            ibsCbsFinalidade: $ibs ? 0 : null,
            ibsCbsIndFinal: $ibs ? 0 : null,
            ibsCbsCodigoIndicadorOperacao: $ibs ? '020101' : '',
            ibsCbsIndDest: $ibs ? 0 : null,
            ibsCbsCst: $ibs ? '000' : '',
            ibsCbsClassificacaoTributaria: $ibs ? '000001' : '',
            substituicao: $substitution,
        );
    }

    private function signer(): DpsSigner
    {
        $cnpj = '11222333000181';
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        $csr = openssl_csr_new(['commonName' => $cnpj], $key, ['digest_alg' => 'sha256']);
        self::assertNotFalse($csr);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        self::assertNotFalse($cert);
        $pfx = '';
        self::assertTrue(openssl_pkcs12_export($cert, $pfx, $key, 'testpass'));
        $this->pfxPath = sys_get_temp_dir() . '/nfse_decision_' . bin2hex(random_bytes(4)) . '.pfx';
        file_put_contents($this->pfxPath, $pfx);
        $store = new NoOpSecretStore();
        $store->put('pfx/' . $cnpj, ['pfx_path' => $this->pfxPath, 'password' => 'testpass']);
        return new DpsSigner($store);
    }
}
