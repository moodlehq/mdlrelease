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

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Helper::class)]
final class HelperTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        date_default_timezone_set('Australia/Perth');
    }

    #[DataProvider('validTypeProvider')]
    public function testIsTypeValid(string $type, bool $expected): void
    {
        $this->assertEquals($expected, Helper::isTypeValid($type));
    }

    #[DataProvider('validTypeProvider')]
    public function testRequireTypeValid(string $type, bool $expected): void
    {
        if ($expected) {
            $this->assertTrue(Helper::requireTypeValid($type));
        } else {
            $this->expectException(\Exception::class);
            Helper::requireTypeValid($type);
        }
    }

    public static function validTypeProvider(): array
    {
        return [
            ['weekly', true],
            ['minor', true],
            ['major', true],
            ['beta', true],
            ['rc', true],
            ['on-demand', true],
            ['on-sync', true],
            ['back-to-dev', true],
            ['faketype', false,]
        ];
    }

    #[DataProvider('getValidatedVersionNumberProvider')]
    public function testGetValidatedVersionNumber(
        int $int,
        int $dec,
        int $expectedint,
        string $expecteddec
    ): void {
        [
            'versionint' => $newint,
            'versiondec' => $newdec,
        ] = Helper::getValidatedVersionNumber($int, $dec);
        $this->assertSame($expectedint, $newint);
        $this->assertSame($expecteddec, $newdec);
    }

    public static function getValidatedVersionNumberProvider(): array
    {
        date_default_timezone_set('Australia/Perth');
        $now = new \DateTimeImmutable();
        $today = $now->format('Ymd') * 100;
        $yesterday = $now->modify('-1 day')->format('Ymd') * 100;
        $tomorrow = $now->modify('+1 day')->format('Ymd') * 100;

        return [
            'Valid yesterday' => [
                $yesterday,
                0,
                $today,
                '00',
            ],
            'Valid today' => [
                $today,
                0,
                $today,
                '01',
            ],
            'Valid today with increment' => [
                $today + 1,
                0,
                $today + 1,
                '01',
            ],
            'End of today' => [
                $today,
                99,
                $today + 1,
                '00',
            ],
            'Tomorrow' => [
                $tomorrow,
                0,
                $tomorrow,
                '01',
            ],
        ];
    }

    #[DataProvider('stableVersionProvider')]
    public function testIsBranchStable(
        string $branch,
        bool $isdevbranch,
        bool $expected,
    ): void {
        $this->assertSame($expected, Helper::isBranchStable($branch, $isdevbranch));
    }

    public static function stableVersionProvider(): array
    {
        return [
            'main' => ['main', true, false],
            'master' => ['master', true, false],
            'MOODLE_401_STABLE' => ['MOODLE_401_STABLE', false, true],
            'MOODLE_500_STABLE' => ['MOODLE_500_STABLE', false, true],
            'MOODLE_500_STABLE in parallel develoipment' => ['MOODLE_500_STABLE', true, false],
        ];
    }

    #[DataProvider('isBranchNameValidProvider')]
    public function testIsBranchNameValid(
        string $name,
        bool $expected,
    ): void {
        $this->assertEquals($expected, Helper::isBranchNameValid($name));
    }

    #[DataProvider('isBranchNameValidProvider')]
    public function testRequireBranchNameValid(
        string $name,
        bool $expected,
    ): void {
        if ($expected) {
            $this->assertTrue(Helper::requireBranchNameValid($name));
        } else {
            $this->expectException(\Exception::class);
            Helper::requireBranchNameValid($name);
        }
    }

    public static function isBranchNameValidProvider(): array
    {
        return [
            'master' => ['master', false],
            'MOODLE_19_STABLE' => ['MOODLE_19_STABLE', false],
            'main' => ['main', true],
            'MOODLE_401_STABLE' => ['MOODLE_401_STABLE', true],
            'MOODLE_500_STABLE' => ['MOODLE_500_STABLE', true],
            'MOODLE_500_STABLE in parallel develoipment' => ['MOODLE_500_STABLE', true],
        ];
    }

    #[DataProvider('pathValidProvider')]
    public function testIsPathValid(
        string $path,
        bool $expected,
    ): void {
        // Set up a virtual file system.
        $root = vfsStream::setup('root', structure: [
            'path' => [
                'to' => [
                    'unreadable' => '',
                    'readable' => [
                        'not' => [
                            'writeable' => '',
                        ],
                        'and' => [
                            'writeable' => '',
                        ],
                    ],
                ],
            ],
        ]);
        $root->getChild('path/to/unreadable')->chmod(0000);
        $root->getChild('path/to/readable/not/writeable')->chmod(0444);
        $root->getChild('path/to/readable/and/writeable')->chmod(0666);

        $path = vfsStream::url("root/{$path}");
        $this->assertEquals(
            $expected,
            Helper::isPathValid($path),
        );
    }

    #[DataProvider('pathValidProvider')]
    public function testRequirePathValid(
        string $path,
        bool $expected,
        ?string $message,
    ): void {
        // Set up a virtual file system.
        $root = vfsStream::setup('root', structure: [
            'path' => [
                'to' => [
                    'unreadable' => '',
                    'readable' => [
                        'not' => [
                            'writeable' => '',
                        ],
                        'and' => [
                            'writeable' => '',
                        ],
                    ],
                ],
            ],
        ]);
        $root->getChild('path/to/unreadable')->chmod(0000);
        $root->getChild('path/to/readable/not/writeable')->chmod(0444);
        $root->getChild('path/to/readable/and/writeable')->chmod(0666);

        $path = vfsStream::url("root/{$path}");
        if ($expected) {
            $this->assertTrue(Helper::requirePathValid($path));
        } else {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage($message);
            Helper::requirePathValid($path);
        }
    }

    public static function pathValidProvider(): array
    {
        return [
            'Not readable' => [
                'path/to/unreadable',
                false,
                'Invalid path given.'
            ],
            'Not writeable' => [
                'path/to/readable/not/writeable',
                false,
                'Path cannot be written to.',
            ],
            'Readable and writable' => [
                'path/to/readable/and/writeable',
                true,
                null,
            ],
        ];
    }

    #[DataProvider('versionFileProvider')]
    public function testIsVersionFileValid(
        string $content,
        bool $expected,
    ): void {
        $this->assertEquals(
            $expected,
            Helper::isVersionFileValid($content),
        );
    }

    #[DataProvider('versionFileProvider')]
    public function testRequireVersionFileValid(
        string $content,
        bool $expected,
    ): void {
        if ($expected) {
            $this->assertTrue(Helper::requireVersionFileValid($content));
        } else {
            $this->expectException(\Exception::class);
            Helper::requireVersionFileValid($content);
        }
    }

    public static function versionFileProvider(): array
    {
        return [
            'Valid version file' => [
                'content' => <<<EOF
                    defined('MOODLE_INTERNAL') || die();

                    \$version  = 2024092900.01;              // YYYYMMDD      = weekly release date of this DEV branch.
                                                            //         RR    = release increments - 00 in DEV branches.
                                                            //           .XX = incremental changes.
                    \$release  = '4.5beta (Build: 20240928)'; // Human-friendly version name
                    \$branch   = '405';                     // This version's branch.
                    \$maturity = MATURITY_BETA;             // This version's maturity level.
                EOF,
                'expected' => true,
            ],
            'Missing version' => [
                'content' => <<<EOF
                    \$release  = '4.5beta (Build: 20240928)'; // Human-friendly version name
                    \$branch   = '405';                     // This version's branch.
                    \$maturity = MATURITY_BETA;             // This version's maturity level.
                EOF,
                'expected' => false,
            ],
            'Missing release' => [
                'content' => <<<EOF
                    \$version  = 2024092900.01;              // YYYYMMDD      = weekly release date of this DEV branch.
                    \$branch   = '405';                     // This version's branch.
                    \$maturity = MATURITY_BETA;             // This version's maturity level.
                EOF,
                'expected' => false,
            ],
            'Missing branch' => [
                'content' => <<<EOF
                    \$version  = 2024092900.01;              // YYYYMMDD      = weekly release date of this DEV branch.
                    \$release  = '4.5beta (Build: 20240928)'; // Human-friendly version name
                    \$maturity = MATURITY_BETA;             // This version's maturity level.
                EOF,
                'expected' => false,
            ],
            'Missing maturity' => [
                'content' => <<<EOF
                    \$version  = 2024092900.01;              // YYYYMMDD      = weekly release date of this DEV branch.
                    \$release  = '4.5beta (Build: 20240928)'; // Human-friendly version name
                    \$branch   = '405';                     // This version's branch.
                EOF,
                'expected' => false,
            ],
        ];
    }

    #[DataProvider('getNextBranchNumberProvider')]
    public function testGetNextBranchNumber(
        int $current,
        int $expected,
    ): void {
        $this->assertSame(
            $expected,
            Helper::getNextBranchNumber($current),
        );
    }

    public static function getNextBranchNumberProvider(): array
    {
        return [
            '404' => [404, 405],
            '405' => [405, 500],
            '500' => [500, 501],
            '501' => [501, 502],
            '502' => [502, 503],
            '503' => [503, 600],
        ];
    }

    #[DataProvider('validOptionProvider')]
    public function testGetOption(
        array $options,
        string $long,
        string $short,
        mixed $expected,
    ): void {
        $this->assertSame(
            $expected,
            Helper::getOption($options, $short, $long),
        );
    }
    public static function validOptionProvider(): array
    {
        return [
            [
                'options' => ['long' => 'value', 'other' => 'other'],
                'long' => 'long',
                'short' => 'l',
                'expected' => 'value',
            ],
            [
                'options' => ['long' => 'value', 'other' => '9999'],
                'long' => 'other',
                'short' => 'o',
                'expected' => '9999',
            ],
        ];
    }

    #[DataProvider('invalidOptionProvider')]
    public function testInvalidGetOption(
        array $options,
        string $long,
        string $short,
        string $expected,
    ): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($expected);
        Helper::getOption($options, $short, $long);
    }

    public static function invalidOptionProvider(): array
    {
        return [
            [
                'options' => ['long' => 'value', 'l' => 'other'],
                'long' => 'long',
                'short' => 'l',
                'expected' => 'Option -l|--long specified more than once.',
            ],
            [
                'options' => ['long' => 'value', 'other' => '9999'],
                'long' => 'asdf',
                'short' => 'a',
                'expected' => 'Required option -a|--asdf must be provided.',
            ],
        ];
    }

    #[DataProvider('getVersionPathProvider')]
    public function testGetVersionPath(
        array $structure,
        string $path,
        ?string $expected,
    ): void {
        $root = vfsStream::setup('root', structure: $structure);

        if ($expected === null) {
            $this->expectException(\ValueError::class);
            $this->expectExceptionMessage("Unable to find a version.php in {$path}");
            Helper::getVersionPath($path);
        } else {
            $this->assertSame($expected, Helper::getVersionPath($path));
        }
    }

    public static function getVersionPathProvider(): \Generator
    {
        yield 'Valid path' => [
            'structure' => [
                'version.php' => '<?php // Version file content.',
            ],
            'path' => vfsStream::url('root'),
            'expected' => vfsStream::url('root/version.php'),
        ];

        yield 'Valid path with trailing slash' => [
            'structure' => [
                'version.php' => '<?php // Version file content.',
            ],
            'path' => vfsStream::url('root/'),
            'expected' => vfsStream::url('root/version.php'),
        ];

        yield 'Valid path in public' => [
            'structure' => [
                'public' => [
                    'version.php' => '<?php // Version file content.',
                ],
            ],
            'path' => vfsStream::url('root'),
            'expected' => vfsStream::url('root/public/version.php'),
        ];

        yield 'Valid path in public with trailing slash' => [
            'structure' => [
                'public' => [
                    'version.php' => '<?php // Version file content.',
                ],
            ],
            'path' => vfsStream::url('root/'),
            'expected' => vfsStream::url('root/public/version.php'),
        ];

        yield 'Invalid path' => [
            'structure' => [],
            'path' => vfsStream::url('root'),
            'expected' => null,
        ];

        yield 'Invalid path in public' => [
            'structure' => [
                'public' => [],
            ],
            'path' => vfsStream::url('root'),
            'expected' => null,
        ];
    }
}
