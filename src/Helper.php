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

namespace MoodleHQ\MoodleRelease;

use Exception;

/**
 * Helper library for Moodle Release scripts.
 *
 * @copyright 2024 Andrew Lyons <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Helper
{
    /**
     * Bump the version.
     *
     * @param  string $path        The path to the versio file
     * @param  string $branch      The branch name to set
     * @param  string $type        The type of release
     * @param  string $rc          If a release candidate, the RC number
     * @param  string $date        The date to use for the version
     * @param  bool   $isdevbranch Whether this is a developmentbranch
     * @throws Exception
     * @return string
     */
    public static function bumpVersion(
        string $path,
        string $branch,
        string $type,
        string $rc,
        string $date,
        bool $isdevbranch,
    ): string {
        // Require that the new branch name is valid.
        self::requireBranchNameValid($branch);
        self::requireTypeValid($type);
        self::requirePathValid($path);

        $currentVersionInfo = VersionInfo::fromVersionFile($path);
        $newVersionInfo = $currentVersionInfo->getNextVersion($branch, $type, $rc, $isdevbranch, $date);

        file_put_contents($path, $newVersionInfo->generateVersionFile());

        return $newVersionInfo->release;
    }

    /**
     * Determine the next branch number based on the current one.
     *
     * Note: This function is valid for Moodle 4.0 and later and follows
     * the Moodle versioning scheme.
     *
     * If there is an exceptional release and the life of a branch is extended
     * this function will not work as expected. If this happens this function
     * will need to be extended as appropriate in the circumstances.
     *
     * @param  int $branch The branch number in integer form
     * @return int The new branch number
     */
    public static function getNextBranchNumber(
        int $branch,
    ): int {
        if ($branch <= 404) {
            return $branch + 1;
        }

        $releasemajor = (int) substr((string) $branch, 0, 1);
        $releaseminor = (int) substr((string) $branch, 2, 1);

        if ($releaseminor >= 3) {
            $releasemajor++;
            $releaseminor = 0;
        } else {
            $releaseminor++;
        }

        return (int) sprintf('%d%02d', $releasemajor, $releaseminor);
    }

    /**
     * Ensure that the buymped version is higher than the current one.
     *
     * @param  int $versionint The integer part of the version
     * @param  string|int $versiondec The decimal part of the version
     * @return array<string, int|string>
     */
    public static function getValidatedVersionNumber(
        int $versionint,
        string|int $versiondec,
    ): array {
        $today = date('Ymd');
        $versiondec = (int) $versiondec;

        if ($versionint >= $today * 100) {
            // Integer version is already past today * 100, increment version decimal part instead of integer part.
            $versiondec = (int) $versiondec + 1;
        } else {
            $versionint = $today . '00';
            $versiondec = 0;
        }
        if ($versiondec >= 100) {
            // Decimal version is already past 99, increment integer part and reset decimal part.
            $versionint = (int) $versionint + 1;
            $versiondec = 0;
        }

        return [
            'versionint' => (int) $versionint,
            'versiondec' => (string) sprintf("%'02d", $versiondec),
        ];
    }

    /**
     * Check if the branch is a stable branch.
     *
     * @param  string $branch      The branch name
     * @param  bool   $isdevbranch Whether the branch is a development branch
     * @return bool
     */
    public static function isBranchStable(
        string $branch,
        bool $isdevbranch,
    ): bool {
        return (strpos($branch, '_STABLE') !== false && !$isdevbranch);
    }

    /**
     * Check whether a branch name is valid.
     *
     * @param  string $branch The branch name
     * @return bool
     */
    public static function isBranchNameValid(
        string $branch,
    ): bool {
        if (str_contains($branch, 'MOODLE_19_STABLE')) {
            // Moodle 1.9 is no longer supported by this tooling.
            return false;
        }

        if ($branch === 'main') {
            return true;
        }

        return !!(preg_match('#^(MOODLE_(\d+)_STABLE)$#', $branch, $matches));
    }

    /**
     * Ensure the branch name is valid.
     *
     * @param  string $branch The branch name
     * @throws Exception
     */
    public static function requireBranchNameValid(
        string $branch,
    ): void {
        if (!self::isBranchNameValid($branch)) {
            throw new Exception('Invalid branch given', __LINE__);
        }
    }

    /**
     * Check whether the type is valid.
     *
     * @param  string $type The type of the release
     * @return bool
     */
    public static function isTypeValid(
        string $type,
    ): bool {
        $types = ['weekly', 'minor', 'major', 'beta', 'rc', 'on-demand', 'on-sync', 'back-to-dev'];
        return in_array($type, $types);
    }

    /**
     * Ensure the type is valid.
     *
     * @param  string $type The type of the release
     * @throws Exception
     */
    public static function requireTypeValid(
        string $type,
    ): void {
        if (!self::isTypeValid($type)) {
            throw new Exception('Invalid type given.', __LINE__);
        }
    }

    /**
     * Check whether the path is valid.
     *
     * @param  string $path The path to the version file
     * @return bool
     */
    public static function isPathValid(
        string $path,
    ): bool {
        if (file_exists($path) && is_readable($path)) {
            if (is_writable($path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ensure the path is valid.
     *
     * @param  string $path The path to the version file
     * @throws Exception
     */
    public static function requirePathValid(
        string $path,
    ): void {
        if (file_exists($path) && is_readable($path)) {
            if (is_writable($path)) {
                return;
            }
            throw new Exception('Path cannot be written to.', __LINE__);
        }
        throw new Exception('Invalid path given.', __LINE__);
    }

    /**
     * Validate the version file.
     *
     * @param  string $contents The contents of the version file
     * @return bool
     */
    public static function isVersionFileValid(
        string $contents,
    ): bool {
        $hasversion = strpos($contents, '$version ') !== false;
        $hasrelease = strpos($contents, '$release ') !== false;
        $hasbranch = strpos($contents, '$branch ') !== false;
        $hasmaturity = strpos($contents, '$maturity ') !== false;

        if ($hasversion && $hasrelease && $hasbranch && $hasmaturity) {
            return true;
        }

        return false;
    }

    /**
     * Ensure the version file is valid.
     *
     * @param  string $contents The contents of the version file
     * @throws Exception
     */
    public static function requireVersionFileValid(
        string $contents,
    ): void {
        if (!self::isVersionFileValid($contents)) {
            throw new Exception('Invalid version file found.', __LINE__);
        }
    }

    /**
     * Get the value of an option from the options array.
     *
     * @param  array<string, string>  $options The options configuration
     * @param  string $short   The short name of the option
     * @param  string $long    The long name of the option
     * @return mixed
     */
    public static function getOption(
        array $options,
        string $short,
        string $long,
    ): mixed {
        if (!isset($options[$short]) && !isset($options[$long])) {
            throw new Exception("Required option -$short|--$long must be provided.", __LINE__);
        }
        if (array_key_exists($short, $options) && array_key_exists($long, $options)) {
            throw new Exception("Option -$short|--$long specified more than once.", __LINE__);
        }
        return (isset($options[$short])) ? $options[$short] : $options[$long];
    }
}
