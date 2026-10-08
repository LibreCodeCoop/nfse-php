<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace LibreCodeCoop\NfsePHP\Tools\Tests;

use LibreCodeCoop\NfsePHP\Tools\PhpVersionPolicy;
use PHPUnit\Framework\TestCase;

final class PhpVersionPolicyTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function example(): array
    {
        return [
            'require' => ['php' => '^8.2'],
            'extra' => [
                'ci' => ['php-matrix' => ['8.2', '8.3', '8.4'], 'php-default' => '8.3'],
            ],
        ];
    }

    public function testRepositoryPolicyUsesRootComposerConstraint(): void
    {
        $policy = PhpVersionPolicy::fromFile(dirname(__DIR__, 2) . '/composer.json');
        self::assertSame('8.2', $policy->minimum());
        self::assertSame(['8.2', '8.3', '8.4'], $policy->matrix());
        self::assertSame('8.3', $policy->primary());
    }

    public function testRequirementChangeMustNotSilentlyLeaveOldMatrix(): void
    {
        $composer = self::example();
        $composer['require']['php'] = '^8.3';
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('PHP matrix version is outside');
        new PhpVersionPolicy($composer);
    }

    public function testRefusesToGuessFromComplexComposerConstraints(): void
    {
        $composer = self::example();
        $composer['require']['php'] = '>=8.2 <9.0';
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unsupported root require.php constraint');
        new PhpVersionPolicy($composer);
    }

    public function testRejectsDuplicatedPhpVersions(): void
    {
        $composer = self::example();
        $composer['extra']['ci']['php-matrix'] = ['8.2', '8.3', '8.3'];
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('unique ascending');
        new PhpVersionPolicy($composer);
    }

    public function testPrimaryVersionMustBelongToCompatibilityMatrix(): void
    {
        $composer = self::example();
        $composer['extra']['ci']['php-default'] = '8.5';
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid extra.ci PHP matrix/default');
        new PhpVersionPolicy($composer);
    }
}
