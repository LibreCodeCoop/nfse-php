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
     * Render one A4 portrait page or fail explicitly. NT 008, item 2.2,
     * forbids multiple-page DANFSe output.
     */
    public function generateFromXml(string $xml): string
    {
        return $this->renderOnePage($xml)[1];
    }

    /**
     * Return exactly the intermediate HTML used for the one-page PDF.
     */
    public function generateHtml(string $xml): string
    {
        return $this->renderOnePage($xml)[0];
    }

    /**
     * Progressive, visible ellipsis is allowed by NT 008, item 2.1. Ordinary
     * invoices keep their full description. When a layout exceeds one page,
     * trim only free-text descriptions, never keys, fields or fiscal totals.
     * If even the minimum readable text does not fit, fail rather than
     * silently returning a second page or hiding required fiscal fields.
     *
     * @return array{string, string} HTML and PDF bytes of the same rendering
     */
    private function renderOnePage(string $xml): array
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

        $this->assertAuthorizedNfse($data);
        $template = new DanfseTemplate();

        // [service-description limit, additional free-text limit].
        // These are adaptive budgets, NOT new fiscal-field constraints.
        foreach ([
            [null, null],
            [1300, 1900],
            [850, 1100],
            [600, 800],
            [400, 500],
            [250, 300],
            [150, 150],
            [60, 60],
        ] as [$serviceLimit, $complementaryLimit]) {
            $html = $template->render($data, $this->config, $serviceLimit, $complementaryLimit);

            try {
                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', false);
                $options->set('defaultFont', 'Helvetica');
                if ($this->config->fonts !== null) {
                    // Permit only the caller-provided, validated font
                    // directories, not arbitrary filesystem access.
                    $options->setChroot(array_values(array_unique(array_merge(
                        $options->getChroot(),
                        $this->config->fonts->directories(),
                    ))));
                    // Isolate dynamic font metrics from Dompdf's bundled
                    // cache. Without this, later default-font documents can
                    // silently inherit a different typeface and overflow A4.
                    $options->setFontDir($this->config->fonts->fontCacheDirectory);
                    $options->setFontCache($this->config->fonts->fontCacheDirectory);
                }

                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html, 'UTF-8');
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
            } catch (\Throwable $e) {
                throw new ArtifactException(
                    'Failed to render DANFSe PDF: ' . $e->getMessage(),
                    NfseErrorCode::ArtifactRetrievalFailed,
                    previous: $e,
                );
            }

            if ($dompdf->getCanvas()->get_page_count() === 1) {
                return [$html, (string) $dompdf->output()];
            }
        }

        throw new ArtifactException(
            'DANFSe does not fit on one A4 page without suppressing mandatory information.',
            NfseErrorCode::ArtifactRetrievalFailed,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertAuthorizedNfse(array $data): void
    {
        $infNfse = $data['infNFSe'] ?? null;
        if (!is_array($infNfse)) {
            throw $this->invalidNfseXml();
        }

        $id = $infNfse['Id'] ?? null;
        if (!is_string($id) || trim($id) === '' || !str_starts_with(trim($id), 'NFS')) {
            throw $this->invalidNfseXml();
        }

        if (!is_array($infNfse['DPS']['infDPS'] ?? null)) {
            throw $this->invalidNfseXml();
        }
    }

    private function invalidNfseXml(): ArtifactException
    {
        return new ArtifactException(
            'Failed to generate DANFSe: XML does not contain an authorized NFS-e.',
            NfseErrorCode::ArtifactRetrievalFailed,
        );
    }
}
