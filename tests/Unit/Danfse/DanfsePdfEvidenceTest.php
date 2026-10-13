<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tests\Unit\Danfse;

use LibreCodeCoop\NfsePHP\Tests\TestCase;
use LibreCodeCoop\NfsePHP\Tools\DanfsePdfEvidence;

require_once dirname(__DIR__, 3) . '/tools/src/DanfsePdfEvidence.php';

/** The verifier parser is unit-tested without Poppler or GitHub Actions. */
final class DanfsePdfEvidenceTest extends TestCase
{
    private const A4 = "Pages:           1\nPage size:       595.28 x 841.89 pts (A4)\n";
    private const TEXT = "DATA CIENTIFICAÇÃO\nIDENTIFICAÇÃO E ASSINATURA\nTotais\naproximados dos Tributos\n";
    private const FONTS = <<<'FONTS'
        name                                 type              encoding         emb sub uni object ID
        ------------------------------------ ----------------- ---------------- --- --- --- ---------
        SUBAAB+LiberationSans-Bold           CID TrueType      Identity-H       yes yes yes     10  0
        SUBAAC+LiberationSans                CID TrueType      Identity-H       yes yes yes     15  0
        FONTS;

    public function testActualPdffontsColumnsAndWrappedTextAreAccepted(): void
    {
        self::assertSame([], DanfsePdfEvidence::check('danfse-reference.pdf', self::A4, self::FONTS, self::TEXT));
    }

    public function testUnembeddedFontIsRejected(): void
    {
        $fonts = str_replace('Identity-H       yes yes yes     15', 'Identity-H        no yes yes     15', self::FONTS);
        $errors = DanfsePdfEvidence::check('danfse-reference.pdf', self::A4, $fonts, self::TEXT);
        self::assertContains('Required font is not embedded: LiberationSans', $errors);
    }

    public function testMissingBoldAndUnexpectedFallbackAreRejected(): void
    {
        $fonts = str_replace('SUBAAB+LiberationSans-Bold', 'SUBAAB+Helvetica-Bold', self::FONTS);
        $errors = DanfsePdfEvidence::check('danfse-reference.pdf', self::A4, $fonts, self::TEXT);
        self::assertContains('Unexpected default font: Helvetica-Bold', $errors);
        self::assertContains('Missing embedded font: LiberationSans-Bold', $errors);
    }

    public function testMissingMandatoryTextAndLandscapeAreRejected(): void
    {
        $info = str_replace('595.28 x 841.89', '841.89 x 595.28', self::A4);
        $errors = DanfsePdfEvidence::check('danfse-reference.pdf', $info, self::FONTS, '');
        self::assertContains('Expected A4 portrait (595.28 x 841.89 pt)', $errors);
        self::assertContains('Missing mandatory visible text: DATA CIENTIFICAÇÃO', $errors);
        self::assertContains('Missing mandatory visible text: IDENTIFICAÇÃO E ASSINATURA', $errors);
        self::assertContains('Missing mandatory visible text: Totais aproximados dos Tributos', $errors);
    }

    public function testMultipageAndLongTextWithoutEllipsisAreRejected(): void
    {
        $errors = DanfsePdfEvidence::check(
            'danfse-long-description.pdf',
            str_replace('Pages:           1', 'Pages:           2', self::A4),
            self::FONTS,
            self::TEXT,
        );
        self::assertContains('Expected exactly one PDF page', $errors);
        self::assertContains('Long service description must be visibly abbreviated', $errors);
    }

    public function testCustomFontsPreserveExternalRegistrationCompatibility(): void
    {
        $fonts = str_replace('LiberationSans', 'DejaVuSans', self::FONTS);
        self::assertSame([], DanfsePdfEvidence::check('danfse-font-probe.pdf', self::A4, $fonts, self::TEXT));
    }

    public function testCustomProbeRejectsCoreFontFallback(): void
    {
        $fonts = str_replace('LiberationSans', 'DejaVuSans', self::FONTS);
        $fonts .= "\nHelvetica-Bold Type 1 WinAnsi no no no 27 0\n";
        $errors = DanfsePdfEvidence::check('danfse-font-probe.pdf', self::A4, $fonts, self::TEXT);
        self::assertContains('Unexpected custom font: Helvetica-Bold', $errors);
    }

    public function testMalformedFontMetadataCannotProduceFalseSuccess(): void
    {
        $errors = DanfsePdfEvidence::check('danfse-reference.pdf', self::A4, 'not pdffonts output', self::TEXT);
        self::assertContains('Unrecognized pdffonts row', $errors);
        self::assertContains('No fonts reported by pdffonts', $errors);
    }

    public function testPhysicalMarginRasterRejectsFullBleedAndAcceptsFivePointInset(): void
    {
        self::assertSame([], DanfsePdfEvidence::checkPageMargins(self::pageRaster(10)));
        $errors = DanfsePdfEvidence::checkPageMargins(self::pageRaster(0));
        self::assertCount(4, $errors);
        self::assertStringContainsString('Physical left margin', $errors[0]);
    }

    public function testPhysicalMarginRasterRejectsInvalidData(): void
    {
        self::assertSame(['Invalid grayscale PGM evidence'], DanfsePdfEvidence::checkPageMargins('invalid'));
    }

    private static function pageRaster(int $inset): string
    {
        $width = 1191;
        $height = 1684;
        $pgm = "P5\n{$width} {$height}\n255\n";
        $pixels = str_repeat("\xFF", $width * $height);
        foreach ([
            [$inset, intdiv($height, 2)],
            [$width - 1 - $inset, intdiv($height, 2)],
            [intdiv($width, 2), $inset],
            [intdiv($width, 2), $height - 1 - $inset],
        ] as [$x, $y]) {
            $pixels[$y * $width + $x] = "\x00";
        }

        return $pgm . $pixels;
    }

}
