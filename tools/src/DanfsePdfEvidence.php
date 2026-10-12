<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools;

/**
 * Pure validation of Poppler output for synthetic DANFSe visual fixtures.
 *
 * Keep parsing/expectations here, not in GitHub Actions shell expressions.
 * A caller is responsible for obtaining the output from local PDF tools.
 *
 * @internal Test and CI tooling, not part of the production NFS-e API.
 */
final class DanfsePdfEvidence
{
    /** @var list<string> */
    public const REQUIRED_FILES = [
        'danfse-reference.pdf',
        'danfse-long-description.pdf',
        'danfse-regime-zero.pdf',
        'danfse-regime-nine.pdf',
        'danfse-regime-absent.pdf',
        'danfse-immunity-zero.pdf',
        'danfse-benefit-zero.pdf',
        'danfse-no-iss-incidence.pdf',
        'danfse-font-probe.pdf',
        'danfse-after-custom.pdf',
    ];

    /**
     * @return list<string> Violated invariants, each with an actionable message.
     */
    public static function check(string $filename, string $pdfInfo, string $pdfFonts, string $pdfText): array
    {
        $errors = [];
        if (preg_match('/^Pages:\s*(\d+)\s*$/m', $pdfInfo, $match) !== 1 || $match[1] !== '1') {
            $errors[] = 'Expected exactly one PDF page';
        }

        if (preg_match('/^Page size:\s*([\d.]+)\s+x\s+([\d.]+)\s+pts\s+\(A4\)/m', $pdfInfo, $match) !== 1
            || abs((float) $match[1] - 595.28) > 0.2
            || abs((float) $match[2] - 841.89) > 0.2
        ) {
            $errors[] = 'Expected A4 portrait (595.28 x 841.89 pt)';
        }

        $text = preg_replace('/\s+/u', ' ', $pdfText);
        if (!is_string($text)) {
            $errors[] = 'Invalid UTF-8 text extraction';
            $text = '';
        }
        foreach (['DATA CIENTIFICAÇÃO', 'IDENTIFICAÇÃO E ASSINATURA', 'Totais aproximados dos Tributos'] as $required) {
            if (!str_contains($text, $required)) {
                $errors[] = 'Missing mandatory visible text: ' . $required;
            }
        }
        if ($filename === 'danfse-long-description.pdf' && !str_contains($text, '...')) {
            $errors[] = 'Long service description must be visibly abbreviated';
        }

        $fontRows = [];
        foreach (preg_split('/\R/', trim($pdfFonts)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'name ') || strspn($line, '- ') === strlen($line)) {
                continue;
            }
            // pdffonts "type" and "encoding" contain spaces. The three flags
            // (emb/sub/uni) are aligned from the RIGHT, not at $4 in awk.
            if (preg_match('/^(\S+)\s+.+?\s+(yes|no)\s+(yes|no)\s+(yes|no)\s+\d+\s+\d+$/i', $line, $match) !== 1) {
                $errors[] = 'Unrecognized pdffonts row';
                continue;
            }
            $fontRows[] = [
                'name' => preg_replace('/^[A-Z]{6}\+/', '', $match[1]),
                'embedded' => strtolower($match[2]) === 'yes',
            ];
        }

        if ($fontRows === []) {
            $errors[] = 'No fonts reported by pdffonts';
            return $errors;
        }

        $custom = $filename === 'danfse-font-probe.pdf';
        $family = $custom ? 'DejaVuSans' : 'LiberationSans';
        $seen = [];
        foreach ($fontRows as $font) {
            $name = $font['name'];
            if (in_array($name, [$family, $family . '-Bold'], true)) {
                $seen[$name] = ($seen[$name] ?? false) || $font['embedded'];
                if (!$font['embedded']) {
                    $errors[] = 'Required font is not embedded: ' . $name;
                }
            } elseif (!$custom) {
                // Default DANFSe must never quietly fall back to core fonts.
                $errors[] = 'Unexpected default font: ' . $name;
            }
        }
        foreach ([$family, $family . '-Bold'] as $required) {
            if (!($seen[$required] ?? false)) {
                $errors[] = 'Missing embedded font: ' . $required;
            }
        }
        // The externally configured font probe retains its pre-existing
        // behavior: assert both custom fonts embed, not that ALL text uses them.
        return $errors;
    }
}
