<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Danfse\Config;

/**
 * Local, caller-licensed font files used for strict NT008 typography.
 *
 * The NFS-e package does not distribute proprietary Arial or Microsoft Sans
 * Serif. A caller must provide files that its own font license permits it to
 * embed in PDFs. No remote URL is accepted or requested at render time.
 */
final readonly class DanfseFonts
{
    public string $arialRegular;
    public string $arialBold;
    public string $microsoftSansSerif;
    public string $fontCacheDirectory;

    public function __construct(
        string $arialRegular,
        string $arialBold,
        string $microsoftSansSerif,
        string $fontCacheDirectory,
    ) {
        $cache = realpath($fontCacheDirectory);
        if ($cache === false || !is_dir($cache) || !is_writable($cache)) {
            throw new \InvalidArgumentException('A writable, existing Dompdf font cache directory is required.');
        }

        $this->fontCacheDirectory = $cache;
        $this->arialRegular = self::existingFont($arialRegular);
        $this->arialBold = self::existingFont($arialBold);
        $this->microsoftSansSerif = self::existingFont($microsoftSansSerif);
    }

    /**
     * @return list<string>
     */
    public function directories(): array
    {
        return array_values(array_unique([
            dirname($this->arialRegular),
            dirname($this->arialBold),
            dirname($this->microsoftSansSerif),
        ]));
    }

    /**
     * Output references files on disk, never inline font bytes or network URIs.
     */
    public function fontFaceCss(): string
    {
        // Dompdf 3.x caches FontMetrics::getFont() lookups statically
        // by CSS family name, across renderer instances in one PHP process.
        // A namespaced family avoids poisoning the next default document.
        $label = $this->labelFamily();
        $value = $this->valueFamily();

        $faces = [
            '@font-face { font-family: "' . $label . '"; font-style: normal; font-weight: 400; src: url("' .
                self::fileUri($this->arialRegular) . '") format("truetype"); }',
            '@font-face { font-family: "' . $label . '"; font-style: normal; font-weight: 700; src: url("' .
                self::fileUri($this->arialBold) . '") format("truetype"); }',
            '@font-face { font-family: "' . $value . '"; font-style: normal; font-weight: 400; src: url("' .
                self::fileUri($this->microsoftSansSerif) . '") format("truetype"); }',
        ];
        // The public custom-font contract supplies a bold label face, but no
        // separate bold value face. Explicitly register the available bold
        // file for highlighted values instead of permitting Helvetica fallback.
        $faces[] = '@font-face { font-family: "' . $value . '"; font-style: normal; font-weight: 700; src: url("' .
            self::fileUri($this->arialBold) . '") format("truetype"); }';

        return implode("\n", $faces);
    }

    public function fontOverrideCss(): string
    {
        return 'body, .label, .fiscal-state-watermark, .watermark { font-family: "' .
            $this->labelFamily() . '", sans-serif; }' . "\n" .
            '.value, .municipality-cell { font-family: "' . $this->valueFamily() . '", sans-serif; }';
    }

    public function labelFamily(): string
    {
        return 'NfseLabels' . $this->cacheNamespace();
    }

    public function valueFamily(): string
    {
        return 'NfseValues' . $this->cacheNamespace();
    }

    private function cacheNamespace(): string
    {
        return substr(hash('sha256', implode('|', [
            $this->arialRegular,
            $this->arialBold,
            $this->microsoftSansSerif,
            $this->fontCacheDirectory,
        ])), 0, 16);
    }

    private static function existingFont(string $path): string
    {
        $real = realpath($path);
        if ($real === false || !is_file($real) || !is_readable($real)
            || preg_match('/\.ttf$/iD', $real) !== 1
        ) {
            throw new \InvalidArgumentException('An existing, readable local TrueType font file is required.');
        }

        return $real;
    }

    private static function fileUri(string $path): string
    {
        // realpath() above guarantees an absolute local path; encode
        // whitespace and quotes so this cannot inject arbitrary CSS.
        return 'file://' . str_replace('%2F', '/', rawurlencode($path));
    }
}
