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
 * Version Information.
 *
 * @copyright  2024 Andrew Lyons <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class VersionInfo
{
    public function __construct(
        public readonly int $integerversion,
        public readonly string $decimalversion,
        public readonly string $comment,
        public readonly string $release,
        public readonly string $build,
        public readonly string $branch,
        public readonly string $maturity,
        public readonly string $branchquote,
        public readonly string $releasequote,
    ) {
    }

    public static function fromVersionFile(string $versionfile, string $branch): self {
        Helper::requireVersionFileValid($versionfile, $branch);

        $is19 = ($branch === 'MOODLE_19_STABLE');

        if (!preg_match('#^ *\$version *= *(?P<integer>\d{10})\.(?P<decimal>\d{2})\d?[^\/]*(?P<comment>/[^\n]*)#m', $versionfile, $matches)) {
            throw new Exception('Could not determine version.', __LINE__);
        }
        $integerversion = $matches['integer'];
        $decimalversion = $matches['decimal'];
        $comment = $matches['comment'];

        if (!preg_match('#^ *\$release *= *(?P<quote>\'|")(?P<release>[^ \+]+\+?) *\(Build: (?P<build>\d{8})\)\1#m', $versionfile, $matches)) {
            throw new Exception('Could not determine the release.', __LINE__);
        }
        $release = $matches['release'];
        $releasequote = $matches['quote'];
        $buildcurrent = $matches['build'];

        if (!$is19) {
            if (!preg_match('# *\$branch *= *(?P<quote>\'|")(?P<branch>\d+)\1#m', $versionfile, $matches)) {
                throw new Exception('Could not determine branch.', __LINE__);
            }
            $branchquote = $matches['quote'];
            $branch = $matches['branch'];
            if (!preg_match('# *\$maturity *= *(?P<maturity>MATURITY_[A-Z]+)#m', $versionfile, $matches)) {
                throw new Exception('Could not determine maturity.', __LINE__);
            }
            $maturity = $matches['maturity'];
        }

        return new self(
            integerversion: $integerversion,
            decimalversion: $decimalversion,
            comment: $comment,
            release: $release,
            build: $buildcurrent,
            branch: $branch,
            maturity: $maturity,
            branchquote: $branchquote,
            releasequote: $releasequote,
        );
    }

    public function getNextVersion(
        string $branch,
        string $type,
        string $rc,
        bool $isdevbranch,
        ?string $date = null,
    ): self {
        $today = date('Ymd');
        $isstable = Helper::isBranchStable($branch, $isdevbranch);
        $build = empty($date) ? $today : $date; // Observe forced date.

        $release = $this->release;
        $decimalversion = $this->decimalversion;
        $integerversion = $this->integerversion;
        $comment = $this->comment;
        $branchcurrent = $this->branch;
        $maturity = $this->maturity;

        if ($isstable) {
            // It's a stable branch.
            if ($type === 'weekly') {
                // It's a stable branch. We need to bump the minor version and add a + if this was the first
                // weekly release after a major or minor release.
                if (strpos($release, '+') === false) {
                    // Add the +
                    $release = $release .= "+";
                }

                $decimalversion++;
                $maturity = 'MATURITY_STABLE';
            } else if ($type === 'minor' || $type === 'major') {
                // If it's minor fine, it's if major then stable gets a minor release.
                // 2.6+ => 2.6.1
                // 2.6.12+ => 2.6.13
                if (strpos($release, '+') !== false) {
                    // Strip the +1 off
                    $release = substr($release, 0, -1);
                }
                if (preg_match('#^(?P<version>\d+\.\d+)\.(?P<increment>\d+)#', $release, $matches)) {
                    $increment = $matches['increment'] + 1;
                    $release = $matches['version'].'.'.(string)$increment;
                } else {
                    // First minor release on this stable branch. Yay X.Y.1.
                    $release .= '.1';
                }
                $integerversion = (int)$integerversion + 1;
                $integerversion = (string)$integerversion;
                $decimalversion = '00';
                // Now handle build date for releases.
                if (empty($date)) { // If no date has been forced, stable minors always are released on Monday.
                    if ((int)date('N') !== 1) { // If today is not Monday, calculate next one.
                        $build = date('Ymd', strtotime('next monday'));
                    }
                }
            }

        } else {
            // Ok it's a development branch.
            if ($type === 'weekly' || $type === 'minor') {
                // If it's weekly, ok, if it's minor the dev branch doesn't get a minor release so really it's a weekly anyway.
                // It's a dev branch. We need to bump the version, if the version is already higher than today*100 then we need
                // to bump accordingly.
                // If under beta or rc, make weekly behave exactly as on-demand.
                if (strpos($release, 'beta') !== false or strpos($release, 'rc') !== false) {
                    // Add the + if missing.
                    if (strpos($release, '+') === false) {
                        // Add the +
                        $release .= '+';
                    }
                    list($integerversion, $decimalversion) = Helper::getValidatedVersionNumber($integerversion, $decimalversion);
                } else if (strpos($release, 'dev') === false) {
                    // Must be immediately after a major release. Bump the release version and set maturity to Alpha.
                    $release = (float)$release + 0.1;
                    $release = (string)$release.'dev';
                    $maturity = 'MATURITY_ALPHA';
                }
                list($integerversion, $decimalversion) = Helper::getValidatedVersionNumber($integerversion, $decimalversion);
            } else if ($type === 'beta') {
                $release = preg_replace('#^(\d+.\d+) *(dev|beta)\+?#', '$1', $release);
                $branch = $branchcurrent; // Branch doesn't change in beta releases ever.
                $release .= 'beta';
                list($integerversion, $decimalversion) = Helper::getValidatedVersionNumber($integerversion, $decimalversion);
                $maturity = 'MATURITY_BETA';
            } else if ($type === 'rc') {
                $release = preg_replace('#^(\d+.\d+) *(dev|beta|rc\d)\+?#', '$1', $release);
                $branch = $branchcurrent; // Branch doesn't change in rc releases ever.
                $release .= 'rc'.$rc;
                list($integerversion, $decimalversion) = Helper::getValidatedVersionNumber($integerversion, $decimalversion);
                $maturity = 'MATURITY_RC';
            } else if ($type === 'on-demand') {
                // Add the + if missing (normally applies to post betas & rcs only,
                // but it's not wrong to generalize it to any on-demand).
                if (strpos($release, '+') === false) {
                    // Add the +
                    $release .= '+';
                }
                list($integerversion, $decimalversion) = Helper::getValidatedVersionNumber($integerversion, $decimalversion);
            } else if ($type === 'on-sync') {
                $decimalversion++;
            } else if ($type === 'back-to-dev') {
                if (strpos($release, 'dev') === false) { // Ensure it's not a "dev" version already.
                    // Must be immediately after a major release. Bump comment, release and maturity.
                    $comment = '// YYYYMMDD      = weekly release date of this DEV branch.';
                    // Normalise a little bit the release, getting rid of everything after the numerical part.
                    $release = preg_replace('/^([0-9.]+).*$/', '\1', $release);
                    // Split the major and minor parts of the release for further process.
                    list($releasemajor, $releaseminor) = explode('.', $release);
                    $release = $releasemajor . '.' . (++$releaseminor); // Increment to next dev version.
                    $release = $release . 'dev';
                    // The branch is the major followed by 2-chars minor.
                    $branch = $releasemajor . str_pad($releaseminor, 2, '0', STR_PAD_LEFT);
                    $maturity = 'MATURITY_ALPHA';
                    if (empty($date)) { // If no date has been forced, back-to-dev have same build date than majors.
                        if ((int)date('N') !== 1) { // If today is not Monday, calculate next one.
                            $build = date('Ymd', strtotime('next Monday'));
                        }
                    }
                }
            } else if ($type === 'major') {
                // Awesome major release!
                $release = preg_replace('#^(\d+.\d+) *(dev|beta|rc\d+)\+?#', '$1', $release);
                $branch = $branchcurrent; // Branch doesn't change in major releases ever.
                list($integerversion, $decimalversion) = Helper::getValidatedVersionNumber($integerversion, $decimalversion);
                $maturity = 'MATURITY_STABLE';
                // Now handle builddate for releases.
                if (empty($date)) { // If no date has been forced, dev majors always are released on Monday.
                    if ((int)date('N') !== 1) { // If today is not Monday, calculate next one.
                        $build = date('Ymd', strtotime('next Monday'));
                    }
                }
                $comment = '// ' . $build . '      = branching date YYYYMMDD - do not modify!';
                // TODO: Move this to Helper::getValidatedVersionNumber() to keep things clear. Require  params.
                // Also force version for major releases. Must match "next Monday" or --date (if specified)
                if (empty($date)) { // If no date has been forced, dev majors always are released on Monday.
                    if ((int)date('N') !== 1) { // If today is not Monday, calculate next one.
                        $integerversion = date('Ymd', strtotime('next Monday')) . '00';
                    }
                } else {
                    $integerversion = $date . '00'; // Apply $date also to major versions.
                }
                $decimalversion = '00'; // Majors always have the decimal reset to .00.
            } else {
                throw new Exception('Unknown type of release requested.', __LINE__);
            }
        }

        // Replace the old version with the new version.
        if (strlen($decimalversion) === 1) {
            $decimalversion = '0'.$decimalversion;
        }

        return new self(
            integerversion: $integerversion,
            decimalversion: $decimalversion,
            comment: $comment,
            release: $release,
            build: $build,
            branch: $branch,
            maturity: $maturity,
            branchquote: $this->branchquote,
            releasequote: $this->releasequote,
        );
    }
}
