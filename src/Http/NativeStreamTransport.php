<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Http;

use LibreCodeCoop\NfsePHP\Contracts\HttpTransportInterface;
use LibreCodeCoop\NfsePHP\Dto\HttpRequestData;
use LibreCodeCoop\NfsePHP\Dto\HttpResponseData;
use LibreCodeCoop\NfsePHP\Exception\NetworkException;

final class NativeStreamTransport implements HttpTransportInterface
{
    #[\Override]
    public function request(HttpRequestData $request): HttpResponseData
    {
        $httpOptions = [
            'method' => strtoupper($request->method),
            'header' => $this->formatHeaders($request->headers),
            'ignore_errors' => true,
            'timeout' => $request->timeoutSeconds,
        ];

        if ($request->body !== null) {
            $httpOptions['content'] = $request->body;
        }

        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ];

        if ($request->clientCertificatePath !== null && $request->clientPrivateKeyPath !== null) {
            $sslOptions['local_cert'] = $request->clientCertificatePath;
            $sslOptions['local_pk'] = $request->clientPrivateKeyPath;
        }

        $context = stream_context_create([
            'http' => $httpOptions,
            'ssl' => $sslOptions,
        ]);

        $http_response_header = [];
        // PHP stream warnings may contain full URLs, including fiscal identifiers.
        // Do not expose those values through logs or error messages.
        $body = @file_get_contents($request->url, false, $context);
        $status = $this->parseHttpStatus($http_response_header);

        if ($body === false && $status === 0) {
            throw new NetworkException('Failed to connect to fiscal gateway');
        }

        return new HttpResponseData(
            status: $status,
            body: $body === false ? '' : $body,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function formatHeaders(array $headers): string
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines === [] ? '' : implode("\r\n", $lines) . "\r\n";
    }

    /**
     * @param list<string> $headers
     */
    private function parseHttpStatus(array $headers): int
    {
        if (!isset($headers[0])) {
            return 0;
        }

        if (preg_match('/HTTP\/[\d.]+ (\d{3})/', $headers[0], $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
