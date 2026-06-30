<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse;

use Dompdf\Dompdf;
use Dompdf\Options;
use LibreCodeCoop\NfsePHP\Danfse\Config\DanfseConfig;
use LibreCodeCoop\NfsePHP\Exception\ArtifactException;
use LibreCodeCoop\NfsePHP\Exception\NfseErrorCode;

/**
 * Generates the DANFSe (PDF auxiliary document) locally from an authorized
 * NFS-e Nacional XML, replacing the (sunset) ADN generation API.
 *
 * Usage:
 *   $pdf = (new DanfseGenerator())->generateFromXml($nfseXml);
 */
final class DanfseGenerator
{
    public function __construct(
        private readonly DanfseConfig $config = new DanfseConfig(),
    ) {
    }

    /**
     * Render the DANFSe PDF from the NFS-e XML and return its raw bytes.
     */
    public function generateFromXml(string $xml): string
    {
        $html = $this->generateHtml($xml);

        try {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'Helvetica');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return (string) $dompdf->output();
        } catch (\Throwable $e) {
            throw new ArtifactException(
                'Failed to render DANFSe PDF: ' . $e->getMessage(),
                NfseErrorCode::ArtifactRetrievalFailed,
                previous: $e,
            );
        }
    }

    /**
     * Render the intermediate HTML (useful for inspection and testing).
     */
    public function generateHtml(string $xml): string
    {
        try {
            $data = (new XmlToArray())->convert($xml);
        } catch (\Throwable $e) {
            throw new ArtifactException(
                'Failed to parse NFS-e XML for DANFSe generation: ' . $e->getMessage(),
                NfseErrorCode::ArtifactRetrievalFailed,
                previous: $e,
            );
        }

        return (new DanfseTemplate())->render($data, $this->config);
    }
}
