<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace MoodleHQ\MoodleRelease;

#[\PHPUnit\Framework\Attributes\CoversMethod(Helper::class, 'bumpComposerProvides')]
#[\PHPUnit\Framework\Attributes\Medium]
final class HelperComposerTest extends \PHPUnit\Framework\TestCase
{
    /** @var string Test directory for temporary files */
    private string $testDirectory;

    public static function setUpBeforeClass(): void
    {
        date_default_timezone_set('Australia/Perth');
    }

    #[\PHPUnit\Framework\Attributes\Before]
    public function createTestDirectory(): void
    {
        $this->testDirectory = sys_get_temp_dir() . '/mdlrelease-' . uniqid();
    }

    #[\PHPUnit\Framework\Attributes\After]
    public function removeTestDirectory(): void
    {
        if (is_dir($this->testDirectory)) {
            $filesystem = new \Symfony\Component\Filesystem\Filesystem();
            $filesystem->remove($this->testDirectory);
        }
        unset($this->testDirectory);
    }

    public static function bumpComposerProviderBump(): array
    {
        $versionData = [
            'integerversion' => 2026020500,
            'decimalversion' => 0,
            'comment' => '// 20260205      = branching date YYYYMMDD - do not modify!',
            'release' => '5.3dev',
            'build' => '20260205',
            'branch' => 'MOODLE_503_STABLE',
            'maturity' => 'MATURITY_ALPHA',
            'branchquote' => "'",
            'releasequote' => "'",
        ];

        return [
            'Moodle version with provide moodle/lms' => [
                'before' => json_encode([
                    'name' => 'moodle/moodle',
                    'provide' => [
                        'moodle/lms' => '5.2',
                    ],
                ]),
                $versionData,
                'after' => [
                    'name' => 'moodle/moodle',
                    'provide' => [
                        'moodle/lms' => '5.3',
                    ],
                ],
            ],
            'Moodle version with provide moodle/lms but no change expected' => [
                'before' => json_encode([
                    'name' => 'moodle/moodle',
                    'provide' => [
                        'moodle/lms' => '5.3',
                    ],
                ]),
                $versionData,
                'after' => [
                    'name' => 'moodle/moodle',
                    'provide' => [
                        'moodle/lms' => '5.3',
                    ],
                ],
            ],
            'Moodle version with some other provide but not moodle/lms' => [
                'before' => json_encode([
                    'name' => 'moodle/moodle',
                    'provide' => [
                        'something/else' => '5.2',
                    ],
                ]),
                $versionData,
                'after' => [
                    'name' => 'moodle/moodle',
                    'provide' => [
                        'something/else' => '5.2',
                    ],
                ],
            ],
            'Moodle version with no provide' => [
                'before' => json_encode([
                    'name' => 'moodle/moodle',
                ]),
                $versionData,
                'after' => [
                    'name' => 'moodle/moodle',
                ],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bumpComposerProviderBump')]
    public function testComposerProviderBump(
        string $before,
        array $versionArgs,
        array $after,
    ): void {
        // Generate a temporary composer.json file with the provided content.
        // We can't run commands against a vfsStream.
        $filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $filesystem->mkdir(\Symfony\Component\Filesystem\Path::normalize($this->testDirectory));

        // Dump the composer.json file.
        $filesystem->dumpFile($this->testDirectory . '/composer.json', $before);

        // Run composer install to generate the composer.lock file.
        $process = new \Symfony\Component\Process\Process([
            'composer',
            'install',
            '--no-dev',
            '--no-scripts',
            '--no-plugins',
            '--no-autoloader',
            '--no-interaction',
            '--quiet',
        ], $this->testDirectory);
        $process->run();

        $version = new VersionInfo(...$versionArgs);

        // Run the method to update the composer.json file.
        Helper::bumpComposerProvides($this->testDirectory, $version);

        // Load the composer.json file and check the provides section has been updated.
        $composerContent = file_get_contents($this->testDirectory . '/composer.json');
        $composerData = json_decode($composerContent, true);
        $this->assertSame($after, $composerData);
    }

    public function testComposerProviderBumpWithoutComposerJson(): void
    {
        $filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $filesystem->mkdir(\Symfony\Component\Filesystem\Path::normalize($this->testDirectory));

        $filesystem->dumpFile($this->testDirectory . '/composer.json', 'not a valid json');

        $version = new VersionInfo(
            integerversion: 2026020500,
            decimalversion: 0,
            comment: '// 20260205      = branching date YYYYMMDD - do not modify!',
            release: '5.3dev',
            build: '20260205',
            branch: 'MOODLE_503_STABLE',
            maturity: 'MATURITY_ALPHA',
            branchquote: "'",
            releasequote: "'",
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to parse composer.json file: Syntax error');

        // Run the method to update the composer.json file.
        Helper::bumpComposerProvides($this->testDirectory, $version);
    }

    public function testComposerProviderBumpWithBadComposerJson(): void
    {
        $filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $filesystem->mkdir(\Symfony\Component\Filesystem\Path::normalize($this->testDirectory));

        $version = new VersionInfo(
            integerversion: 2026020500,
            decimalversion: 0,
            comment: '// 20260205      = branching date YYYYMMDD - do not modify!',
            release: '5.3dev',
            build: '20260205',
            branch: 'MOODLE_503_STABLE',
            maturity: 'MATURITY_ALPHA',
            branchquote: "'",
            releasequote: "'",
        );

        // Run the method to update the composer.json file.
        Helper::bumpComposerProvides($this->testDirectory, $version);

        // Load the composer.json file and check the provides section has been updated.
        $this->assertFileDoesNotExist($this->testDirectory . '/composer.json');
    }

    public function testComposerProviderBumpWithoutLock(): void
    {
        $before = json_encode([
            'name' => 'moodle/moodle',
            'provide' => [
                'moodle/lms' => '5.2',
            ],
        ]);

        // Generate a temporary composer.json file with the provided content.
        // We can't run commands against a vfsStream.
        $filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $filesystem->mkdir(\Symfony\Component\Filesystem\Path::normalize($this->testDirectory));

        // Dump the composer.json file.
        $filesystem->dumpFile($this->testDirectory . '/composer.json', $before);

        $version = new VersionInfo(
            integerversion: 2026020500,
            decimalversion: 0,
            comment: '// 20260205      = branching date YYYYMMDD - do not modify!',
            release: '5.3dev',
            build: '20260205',
            branch: 'MOODLE_503_STABLE',
            maturity: 'MATURITY_ALPHA',
            branchquote: "'",
            releasequote: "'",
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to update composer.lock file:');

        // Run the method to update the composer.json file.
        Helper::bumpComposerProvides($this->testDirectory, $version);
    }
}
