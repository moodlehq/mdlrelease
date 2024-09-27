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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(VersionInfo::class)]
final class VersionInfoTest extends TestCase
{
    #[DataProvider('nextVersionFromMajorProvider')]
    #[DataProvider('nextVersionFromMinorProvider')]
    #[DataProvider('nextVersionFromWeeklyProvider')]
    #[DataProvider('nextVersionFromDevelopmentProvider')]
    #[DataProvider('nextVersionFromBetaProvider')]
    public function testGetNextVersion(
        array $currentVersionArgs,
        array $nextVersionArgs,
        array $expectations,
    ): void
    {
        $version = new VersionInfo(...$currentVersionArgs);
        $nextVersion = $version->getNextVersion(...$nextVersionArgs);

        foreach ($expectations as $property => $expectedValue) {
            self::assertSame($expectedValue, $nextVersion->{$property});
        }
    }

    public static function nextVersionFromMajorProvider(): array
    {
        $majorVersion = [
            'integerversion' => 2024092300,
            'decimalversion' => '00',
            'comment' => '// 20240923      = branching date YYYYMMDD - do not modify!',
            'release' => '4.5',
            'build' => '20240921',
            'branch' => '405',
            'maturity' => 'MATURITY_STABLE',
            'branchquote' => "'",
            'releasequote' => "'",
        ];

        return [
            'Weekly version from major' => [
                $majorVersion,
                [
                    'branch' => 'MOODLE_405_STABLE',
                    'type' => 'weekly',
                    'rc' => '',
                    'date' => '20240926',
                    'isdevbranch' => false,
                ],
                [
                    'integerversion' => 2024092300,
                    'decimalversion' => '01',
                    'release' => '4.5+',
                    'build' => '20240926',
                    'branchquote' => "'",
                    'releasequote' => "'",
                ],
            ],
            'Minor version from major' => [
                $majorVersion,
                [
                    'branch' => 'MOODLE_405_STABLE',
                    'type' => 'minor',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => false,
                ],
                [
                    'integerversion' => 2024092301,
                    'decimalversion' => '00',
                    'release' => '4.5.1',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                ],
            ],
            'Major version from major' => [
                // Note: A Major release also includes minors for stable branches.
                $majorVersion,
                [
                    'branch' => 'MOODLE_405_STABLE',
                    'type' => 'major',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => false,
                ],
                [
                    'integerversion' => 2024092301,
                    'decimalversion' => '00',
                    'release' => '4.5.1',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                ],
            ],
            'Development version from major' => [
                $majorVersion,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'weekly',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => date('Ymd') * 100,
                    'decimalversion' => '00',
                    'release' => '4.6dev', // Note: The tooling has not yet been updated to handle the new versioning scheme.
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                ],
            ],
        ];
    }

    public static function nextVersionFromMinorProvider(): array
    {
        $minorVersion = [
            'integerversion' => 2024092301,
            'decimalversion' => '00',
            'comment' => '// 20240923      = branching date YYYYMMDD - do not modify!',
            'release' => '4.5.1',
            'build' => '20240921',
            'branch' => '405',
            'maturity' => 'MATURITY_STABLE',
            'branchquote' => "'",
            'releasequote' => "'",
        ];

        return [
            'Weekly version from minor' => [
                $minorVersion,
                [
                    'branch' => 'MOODLE_405_STABLE',
                    'type' => 'weekly',
                    'rc' => '',
                    'date' => '20240926',
                    'isdevbranch' => false,
                ],
                [
                    'integerversion' => 2024092301,
                    'decimalversion' => '01',
                    'release' => '4.5.1+',
                    'build' => '20240926',
                    'branchquote' => "'",
                    'releasequote' => "'",
                ],
            ],
            'Minor version from minor' => [
                $minorVersion,
                [
                    'branch' => 'MOODLE_405_STABLE',
                    'type' => 'minor',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => false,
                ],
                [
                    'integerversion' => 2024092302,
                    'decimalversion' => '00',
                    'release' => '4.5.2',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                ],
            ],
            'Major version from minor' => [
                $minorVersion,
                [
                    'branch' => 'MOODLE_405_STABLE',
                    'type' => 'major',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => false,
                ],
                [
                    'integerversion' => 2024092302,
                    'decimalversion' => '00',
                    'release' => '4.5.2',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                ],
            ],
        ];
    }
    public static function nextVersionFromWeeklyProvider(): array
    {
        $weeklyVersion = [
            'integerversion' => 2024092301,
            'decimalversion' => '00',
            'comment' => '// 20240923      = branching date YYYYMMDD - do not modify!',
            'release' => '4.5.1+',
            'build' => '20240921',
            'branch' => '405',
            'maturity' => 'MATURITY_STABLE',
            'branchquote' => "'",
            'releasequote' => "'",
        ];

        return [
            'Weekly version from weekly' => [
                $weeklyVersion,
                [
                    'branch' => 'MOODLE_405_STABLE',
                    'type' => 'weekly',
                    'rc' => '',
                    'date' => '20240926',
                    'isdevbranch' => false,
                ],
                [
                    'integerversion' => 2024092301,
                    'decimalversion' => '01',
                    'release' => '4.5.1+',
                    'build' => '20240926',
                    'branchquote' => "'",
                    'releasequote' => "'",
                ],
            ],
            'Minor version from weekly' => [
                $weeklyVersion,
                [
                    'branch' => 'MOODLE_405_STABLE',
                    'type' => 'minor',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => false,
                ],
                [
                    'integerversion' => 2024092302,
                    'decimalversion' => '00',
                    'release' => '4.5.2',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                ],
            ],
            'Major version from weekly' => [
                $weeklyVersion,
                [
                    'branch' => 'MOODLE_405_STABLE',
                    'type' => 'major',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => false,
                ],
                [
                    'integerversion' => 2024092302,
                    'decimalversion' => '00',
                    'release' => '4.5.2',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                ],
            ],
        ];
    }
    public static function nextVersionFromDevelopmentProvider(): array
    {
        $developmentVersion = [
            'integerversion' => 2024092301,
            'decimalversion' => '00',
            'comment' => '// 20240923      = branching date YYYYMMDD - do not modify!',
            'release' => '5.0dev',
            'build' => '20240921',
            'branch' => '500',
            'maturity' => 'MATURITY_ALPHA',
            'branchquote' => "'",
            'releasequote' => "'",
        ];

        return [
            'Weekly version from development' => [
                $developmentVersion,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'weekly',
                    'rc' => '',
                    'date' => '20240926',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => date('Ymd') * 100,
                    'decimalversion' => '00',
                    'release' => '5.0dev',
                    'build' => '20240926',
                    'branchquote' => "'",
                    'releasequote' => "'",
                    'maturity' => 'MATURITY_ALPHA',
                ],
            ],
            'Minor version from development' => [
                // Note: Development versions do not get minor. We treat it as a synonym for weekly.
                $developmentVersion,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'minor',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => date('Ymd') * 100,
                    'decimalversion' => '00',
                    'release' => '5.0dev',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                    'maturity' => 'MATURITY_ALPHA',
                ],
            ],
            'Beta version from development' => [
                $developmentVersion,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'beta',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => date('Ymd') * 100,
                    'decimalversion' => '00',
                    'release' => '5.0beta',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                    'maturity' => 'MATURITY_BETA',
                ],
            ],
            'RC version from development' => [
                $developmentVersion,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'rc',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => date('Ymd') * 100,
                    'decimalversion' => '00',
                    'release' => '5.0rc',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                    'maturity' => 'MATURITY_RC',
                ],
            ],
            // 'On-Demand from development' => [
            //     // Note: This is just a standard weekly release of the development branch.
            //     $developmentVersion,
            //     [
            //         'branch' => 'MOODLE_500_STABLE',
            //         'type' => 'on-demand',
            //         'rc' => '',
            //         'date' => '20240923',
            //         'isdevbranch' => true,
            //     ],
            //     [
            //         'integerversion' => date('Ymd') * 100,
            //         'decimalversion' => '00',
            //         'release' => '5.0dev',
            //         'build' => '20240923',
            //         'branchquote' => "'",
            //         'releasequote' => "'",
            //         'maturity' => 'MATURITY_ALPHA', // No change.
            //     ],
            // ],
            'Major version from development' => [
                $developmentVersion,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'major',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => 2024092300,
                    'decimalversion' => '00',
                    'release' => '5.0',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                    'maturity' => 'MATURITY_STABLE',
                ],
            ],
            'Back to dev from development' => [
                $developmentVersion,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'back-to-dev',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => 2024092301,
                    'decimalversion' => '00',
                    'release' => '5.0dev',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                    'maturity' => 'MATURITY_ALPHA',
                ],
            ],
        ];
    }

    public static function nextVersionFromBetaProvider(): array
    {
        $version = [
            'integerversion' => 2024092301,
            'decimalversion' => '00',
            'comment' => '// 20240923      = branching date YYYYMMDD - do not modify!',
            'release' => '5.0beta',
            'build' => '20240921',
            'branch' => '500',
            'maturity' => 'MATURITY_BETA',
            'branchquote' => "'",
            'releasequote' => "'",
        ];

        return [
            'Beta version from beta' => [
                $version,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'beta',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => date('Ymd') * 100,
                    'decimalversion' => '00',
                    'release' => '5.0beta',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                    'maturity' => 'MATURITY_BETA',
                ],
            ],
            'RC version from beta' => [
                $version,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'rc',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => date('Ymd') * 100,
                    'decimalversion' => '00',
                    'release' => '5.0rc',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                    'maturity' => 'MATURITY_RC',
                ],
            ],
            'On-Demand from beta' => [
                // Note: This is just a standard weekly release of the development branch.
                $version,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'on-demand',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => date('Ymd') * 100,
                    'decimalversion' => '00',
                    'release' => '5.0beta+',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                    'maturity' => 'MATURITY_BETA',
                ],
            ],
            'Major version from beta' => [
                $version,
                [
                    'branch' => 'MOODLE_500_STABLE',
                    'type' => 'major',
                    'rc' => '',
                    'date' => '20240923',
                    'isdevbranch' => true,
                ],
                [
                    'integerversion' => 2024092300,
                    'decimalversion' => '00',
                    'release' => '5.0',
                    'build' => '20240923',
                    'branchquote' => "'",
                    'releasequote' => "'",
                    'maturity' => 'MATURITY_STABLE',
                ],
            ],
        ];
    }

    #[DataProvider('versionFileProvider')]
    public function testFromVersionFile(
        string $versionFileName,
        string $branch,
        array $expectations,
    ): void
    {
        $versionFileContent = file_get_contents($versionFileName);
        $version = VersionInfo::fromVersionFile($versionFileContent, $branch);

        foreach ($expectations as $property => $expectedValue) {
            self::assertSame($expectedValue, $version->{$property});
        }
    }

    public static function versionFileProvider(): array
    {
        return [
            '4.3.0' => [
                dirname(__DIR__) . '/fixtures/versions/4.3.0.php',
                'MOODLE_403_STABLE',
                [
                    'integerversion' => 2023100900,
                    'decimalversion' => '00',
                    'release' => '4.3',
                    'build' => '20231009',
                    'branch' => '403',
                    'maturity' => 'MATURITY_STABLE',
                ],
            ],
            '4.3.2' => [
                dirname(__DIR__) . '/fixtures/versions/4.3.2.php',
                'MOODLE_403_STABLE',
                [
                    'integerversion' => 2023100902,
                    'decimalversion' => '00',
                    'release' => '4.3.2',
                    'build' => '20231222',
                    'branch' => '403',
                    'maturity' => 'MATURITY_STABLE',
                ],
            ],
            '4.4.0-beta' => [
                dirname(__DIR__) . '/fixtures/versions/4.4.0-beta.php',
                'MOODLE_404_STABLE',
                [
                    'integerversion' => 2024041200,
                    'decimalversion' => '01',
                    'release' => '4.4beta',
                    'build' => '20240412',
                    'branch' => '404',
                    'maturity' => 'MATURITY_BETA',
                ],
            ],
            '4.4.0-rc1' => [
                dirname(__DIR__) . '/fixtures/versions/4.4.0-rc1.php',
                'MOODLE_404_STABLE',
                [
                    'integerversion' => 2024041600,
                    'decimalversion' => '00',
                    'release' => '4.4rc1',
                    'build' => '20240416',
                    'branch' => '404',
                    'maturity' => 'MATURITY_RC',
                ],
            ],
            '4.4.0' => [
                dirname(__DIR__) . '/fixtures/versions/4.4.0.php',
                'MOODLE_404_STABLE',
                [
                    'integerversion' => 2024042200,
                    'decimalversion' => '00',
                    'release' => '4.4',
                    'build' => '20240422',
                    'branch' => '404',
                    'maturity' => 'MATURITY_STABLE',
                ],
            ],
        ];
    }

    #[DataProvider('invalidVersionFileProvider')]
    public function testFromVersionFileInvalid(
        string $versionFileName,
        string $branch,
        string $expectedExceptionMessage,
    ): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $versionFileContent = file_get_contents($versionFileName);
        VersionInfo::fromVersionFile($versionFileContent, $branch);
    }

    public static function invalidVersionFileProvider(): array
    {
        return [
            'Invalid version file' => [
                dirname(__DIR__) . '/fixtures/versions/invalidVersion.php',
                'MOODLE_404_STABLE',
                'Could not determine version.',
            ],
        ];
    }

}
