<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Http;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use LibreCodeCoop\NfsePHP\Dto\DpsData;
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

    public static function setUpBeforeClass(): void
    {
        self::$server = new MockWebServer();
        self::$server->start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testEmitReturnsReceiptDataOnSuccess(): void
    {
        $payload = json_encode([
            'nNFSe'       => '42',
            'chaveAcesso' => 'abc-123',
            'dhEmi'       => '2026-01-01T12:00:00',
        ], JSON_THROW_ON_ERROR);

        self::$server->setResponseOfPath(
            '/NFS-e/api/v1/dps',
            new Response($payload, ['Content-Type' => 'application/json'], 200)
        );

        $store  = new NoOpSecretStore();
        $client = new NfseClient(
            secretStore:     $store,
            sandboxMode:     false,
            baseUrlOverride: self::$server->getServerRoot() . '/NFS-e/api/v1',
        );

        $dps     = $this->makeDps();
        $receipt = $client->emit($dps);

        self::assertSame('42', $receipt->nfseNumber);
        self::assertSame('abc-123', $receipt->chaveAcesso);
        self::assertSame('2026-01-01T12:00:00', $receipt->dataEmissao);
    }

    public function testQueryReturnsReceiptDataOnSuccess(): void
    {
        $payload = json_encode([
            'nNFSe'       => '99',
            'chaveAcesso' => 'xyz-456',
            'dhEmi'       => '2026-06-01T10:00:00',
        ], JSON_THROW_ON_ERROR);

        self::$server->setResponseOfPath(
            '/NFS-e/api/v1/dps/xyz-456',
            new Response($payload, ['Content-Type' => 'application/json'], 200)
        );

        $store  = new NoOpSecretStore();
        $client = new NfseClient(
            secretStore:     $store,
            baseUrlOverride: self::$server->getServerRoot() . '/NFS-e/api/v1',
        );

        $receipt = $client->query('xyz-456');

        self::assertSame('99', $receipt->nfseNumber);
    }

    public function testCancelReturnsTrueOnSuccess(): void
    {
        self::$server->setResponseOfPath(
            '/NFS-e/api/v1/dps/abc-123',
            new Response('{}', ['Content-Type' => 'application/json'], 200)
        );

        $store  = new NoOpSecretStore();
        $client = new NfseClient(
            secretStore:     $store,
            baseUrlOverride: self::$server->getServerRoot() . '/NFS-e/api/v1',
        );

        self::assertTrue($client->cancel('abc-123', 'Cancelamento a pedido do tomador'));
    }

    // -------------------------------------------------------------------------

    private function makeDps(): DpsData
    {
        return new DpsData(
            cnpjPrestador:   '29842527000145',
            municipioIbge:   '3303302',
            itemListaServico: '0107',
            valorServico:    '1000.00',
            aliquota:        '5.00',
            discriminacao:   'Consultoria em TI',
        );
    }
}
