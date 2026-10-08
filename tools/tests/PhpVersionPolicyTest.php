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
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertSame('^' . $policy->minimum(), $composer['require']['php']);
        self::assertSame($composer['extra']['ci']['php-matrix'], $policy->matrix());
        self::assertSame($composer['extra']['ci']['php-default'], $policy->primary());
        self::assertSame($policy->minimum(), $policy->matrix()[0]);
        self::assertContains($policy->primary(), $policy->matrix());
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

    public function testIsolatedToolingRuntimeMustRespectRootPhpConstraint(): void
    {
        $policy = new PhpVersionPolicy(self::example());
        $policy->assertRuntime('8.2.0');
        $policy->assertRuntime('8.4.24');
        try {
            $policy->assertRuntime('8.1.33');
            self::fail('Runtime below the root requirement must be rejected');
        } catch (\UnexpectedValueException $error) {
            self::assertStringContainsString('not supported', $error->getMessage());
        }
        $this->expectException(\UnexpectedValueException::class);
        $policy->assertRuntime('9.0.0');
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
