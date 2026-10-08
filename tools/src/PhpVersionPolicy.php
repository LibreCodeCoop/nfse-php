<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools;

/**
 * The root Composer manifest owns the PHP support and CI testing policy.
 *
 * "require.php" specifies compatibility, not a pinned runner version.
 */
final class PhpVersionPolicy
{
    private string $minimum;

    /** @var list<string> */
    private array $matrix;

    private string $primary;

    /**
     * @param array<string, mixed> $composer
     */
    public function __construct(array $composer)
    {
        $constraint = $composer['require']['php'] ?? null;
        if (!is_string($constraint)
            || preg_match('/^\\^([1-9][0-9]*)\\.([0-9]+)(?:\\.0)?$/D', $constraint, $parts) !== 1) {
            throw new \UnexpectedValueException(
                'Unsupported root require.php constraint; expected ^MAJOR.MINOR, e.g. ^8.2',
            );
        }

        $this->minimum = (int) $parts[1] . '.' . (int) $parts[2];
        $matrix = $composer['extra']['ci']['php-matrix'] ?? null;
        $primary = $composer['extra']['ci']['php-default'] ?? null;
        if (!is_array($matrix) || !array_is_list($matrix) || $matrix === []
            || !is_string($primary) || !in_array($primary, $matrix, true)) {
            throw new \UnexpectedValueException('Invalid extra.ci PHP matrix/default in root composer.json');
        }

        $previous = null;
        foreach ($matrix as $version) {
            if (!is_string($version) || preg_match('/^[0-9]+\\.[0-9]+$/D', $version) !== 1
                || ($previous !== null && version_compare($version, $previous, '<='))) {
                throw new \UnexpectedValueException('PHP matrix must contain unique ascending major.minor versions');
            }
            if (!str_starts_with($version, $parts[1] . '.')
                || version_compare($version, $this->minimum, '<')) {
                throw new \UnexpectedValueException('PHP matrix version is outside the root Composer constraint');
            }
            $previous = $version;
        }
        if ($matrix[0] !== $this->minimum) {
            throw new \UnexpectedValueException('PHP matrix must begin with the Composer minimum');
        }
        $this->matrix = $matrix;
        $this->primary = $primary;
    }

    public static function fromFile(string $filename): self
    {
        $contents = @file_get_contents($filename);
        if ($contents === false) {
            throw new \RuntimeException('Cannot read root Composer manifest: ' . $filename);
        }
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Root composer.json must contain a JSON object');
        }

        return new self($decoded);
    }

    public function minimum(): string
    {
        return $this->minimum;
    }

    /**
     * @return list<string>
     */
    public function matrix(): array
    {
        return $this->matrix;
    }

    public function primary(): string
    {
        return $this->primary;
    }

    /**
     * Isolated tooling may be installed separately, but must still run on a
     * PHP interpreter supported by this repository's Composer constraint.
     */
    public function assertRuntime(string $version): void
    {
        $major = (int) explode('.', $this->minimum)[0];
        if (version_compare($version, $this->minimum, '<')
            || (int) explode('.', $version)[0] !== $major) {
            throw new \UnexpectedValueException(
                "PHP {$version} is not supported by root composer.json (requires ^{$this->minimum})",
            );
        }
    }
}
