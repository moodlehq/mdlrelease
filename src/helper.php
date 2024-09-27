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

/**
 * Helper library for Moodle Release scripts.
 *
 * @package    core
 * @copyright  2024 Andrew Lyons <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Helper {
    function bump_version($path, $branch, $type, $rc, $date, $isdevbranch) {

        validate_branch($branch);
        validate_type($type);
        validate_path($path);

        $versionfile = file_get_contents($path);

        validate_version_file($versionfile, $branch);

        $is19 = ($branch === 'MOODLE_19_STABLE');
        $isstable = branch_is_stable($branch, $isdevbranch);
        $today = date('Ymd');

        $integerversioncurrent = null;
        $decimalversioncurrent = null;
        $commentcurrent = null;
        $releasecurrent = null;
        $buildcurrent = null;
        $branchcurrent = null;
        $maturitycurrent = null;

        $branchquote = null;
        $releasequote = null;

        $integerversionnew = null;
        $decimalversionnew = null;
        $commentnew = null;
        $releasenew = null;
        $buildnew = null;
        $branchnew = null;
        $maturitynew = null;

        if (!preg_match('#^ *\$version *= *(?P<integer>\d{10})\.(?P<decimal>\d{2})\d?[^\/]*(?P<comment>/[^\n]*)#m', $versionfile, $matches)) {
            throw new Exception('Could not determine version.', __LINE__);
        }
        $integerversionnew = $integerversioncurrent = $matches['integer'];
        $decimalversionnew = $decimalversioncurrent = $matches['decimal'];
        $commentnew = $commentcurrent = $matches['comment'];

        if (!preg_match('#^ *\$release *= *(?P<quote>\'|")(?P<release>[^ \+]+\+?) *\(Build: (?P<build>\d{8})\)\1#m', $versionfile, $matches)) {
            throw new Exception('Could not determine the release.', __LINE__);
        }
        $releasenew = $releasecurrent = $matches['release'];
        $releasequote = $matches['quote'];
        $buildcurrent = $matches['build'];
        $buildnew = empty($date) ? $today : $date; // Observe forced date.

        if (!$is19) {
            if (!preg_match('# *\$branch *= *(?P<quote>\'|")(?P<branch>\d+)\1#m', $versionfile, $matches)) {
                throw new Exception('Could not determine branch.', __LINE__);
            }
            $branchquote = $matches['quote'];
            $branchnew = $branchcurrent = $matches['branch'];
            if (!preg_match('# *\$maturity *= *(?P<maturity>MATURITY_[A-Z]+)#m', $versionfile, $matches)) {
                throw new Exception('Could not determine maturity.', __LINE__);
            }
            $maturitynew = $maturitycurrent = $matches['maturity'];
        }

        if ($isstable) {
            // It's a stable branch.
            if ($type === 'weekly') {
                // It's a stable branch. We need to bump the minor version and add a + if this was the first
                // weekly release after a major or minor release.
                if (strpos($releasenew, '+') === false) {
                    // Add the +
                    $releasenew .= '+';
                }
                $decimalversionnew++;
                $maturitynew = 'MATURITY_STABLE';
            } else if ($type === 'minor' || $type === 'major') {
                // If it's minor fine, it's if major then stable gets a minor release.
                // 2.6+ => 2.6.1
                // 2.6.12+ => 2.6.13
                if (strpos($releasenew, '+') !== false) {
                    // Strip the +1 off
                    $releasenew = substr($releasenew, 0, -1);
                }
                if (preg_match('#^(?P<version>\d+\.\d+)\.(?P<increment>\d+)#', $releasenew, $matches)) {
                    $increment = $matches['increment'] + 1;
                    $releasenew = $matches['version'].'.'.(string)$increment;
                } else {
                    // First minor release on this stable branch. Yay X.Y.1.
                    $releasenew .= '.1';
                }
                $integerversionnew = (int)$integerversionnew + 1;
                $integerversionnew = (string)$integerversionnew;
                $decimalversionnew = '00';
                // Now handle build date for releases.
                if (empty($date)) { // If no date has been forced, stable minors always are released on Monday.
                    if ((int)date('N') !== 1) { // If today is not Monday, calculate next one.
                        $buildnew = date('Ymd', strtotime('next monday'));
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
                if (strpos($releasecurrent, 'beta') !== false or strpos($releasecurrent, 'rc') !== false) {
                    // Add the + if missing.
                    if (strpos($releasenew, '+') === false) {
                        // Add the +
                        $releasenew .= '+';
                    }
                    list($integerversionnew, $decimalversionnew) = bump_dev_ensure_higher($integerversionnew, $decimalversionnew);
                } else if (strpos($releasecurrent, 'dev') === false) {
                    // Must be immediately after a major release. Bump the release version and set maturity to Alpha.
                    $releasenew = (float)$releasenew + 0.1;
                    $releasenew = (string)$releasenew.'dev';
                    $maturitynew = 'MATURITY_ALPHA';
                }
                list($integerversionnew, $decimalversionnew) = bump_dev_ensure_higher($integerversionnew, $decimalversionnew);
            } else if ($type === 'beta') {
                $releasenew = preg_replace('#^(\d+.\d+) *(dev|beta)\+?#', '$1', $releasenew);
                $branchnew = $branchcurrent; // Branch doesn't change in beta releases ever.
                $releasenew .= 'beta';
                list($integerversionnew, $decimalversionnew) = bump_dev_ensure_higher($integerversionnew, $decimalversionnew);
                $maturitynew = 'MATURITY_BETA';
            } else if ($type === 'rc') {
                $releasenew = preg_replace('#^(\d+.\d+) *(dev|beta|rc\d)\+?#', '$1', $releasenew);
                $branchnew = $branchcurrent; // Branch doesn't change in rc releases ever.
                $releasenew .= 'rc'.$rc;
                list($integerversionnew, $decimalversionnew) = bump_dev_ensure_higher($integerversionnew, $decimalversionnew);
                $maturitynew = 'MATURITY_RC';
            } else if ($type === 'on-demand') {
                // Add the + if missing (normally applies to post betas & rcs only,
                // but it's not wrong to generalize it to any on-demand).
                if (strpos($releasenew, '+') === false) {
                    // Add the +
                    $releasenew .= '+';
                }
                list($integerversionnew, $decimalversionnew) = bump_dev_ensure_higher($integerversionnew, $decimalversionnew);
            } else if ($type === 'on-sync') {
                $decimalversionnew++;
            } else if ($type === 'back-to-dev') {
                if (strpos($releasecurrent, 'dev') === false) { // Ensure it's not a "dev" version already.
                    // Must be immediately after a major release. Bump comment, release and maturity.
                    $commentnew = '// YYYYMMDD      = weekly release date of this DEV branch.';
                    // Normalise a little bit the release, getting rid of everything after the numerical part.
                    $releasenew = preg_replace('/^([0-9.]+).*$/', '\1', $releasenew);
                    // Split the major and minor parts of the release for further process.
                    list($releasemajor, $releaseminor) = explode('.', $releasenew);
                    $releasenew = $releasemajor . '.' . (++$releaseminor); // Increment to next dev version.
                    $releasenew = $releasenew . 'dev';
                    // The branch is the major followed by 2-chars minor.
                    $branchnew = $releasemajor . str_pad($releaseminor, 2, '0', STR_PAD_LEFT);
                    $maturitynew = 'MATURITY_ALPHA';
                    if (empty($date)) { // If no date has been forced, back-to-dev have same build date than majors.
                        if ((int)date('N') !== 1) { // If today is not Monday, calculate next one.
                            $buildnew = date('Ymd', strtotime('next Monday'));
                        }
                    }
                }
            } else {
                // Awesome major release!
                $releasenew = preg_replace('#^(\d+.\d+) *(dev|beta|rc\d+)\+?#', '$1', $releasenew);
                $branchnew = $branchcurrent; // Branch doesn't change in major releases ever.
                list($integerversionnew, $decimalversionnew) = bump_dev_ensure_higher($integerversionnew, $decimalversionnew);
                $maturitynew = 'MATURITY_STABLE';
                // Now handle builddate for releases.
                if (empty($date)) { // If no date has been forced, dev majors always are released on Monday.
                    if ((int)date('N') !== 1) { // If today is not Monday, calculate next one.
                        $buildnew = date('Ymd', strtotime('next Monday'));
                    }
                }
                $commentnew = '// ' . $buildnew . '      = branching date YYYYMMDD - do not modify!';
                // TODO: Move this to bump_dev_ensure_higher() to keep things clear. Require new params.
                // Also force version for major releases. Must match "next Monday" or --date (if specified)
                if (empty($date)) { // If no date has been forced, dev majors always are released on Monday.
                    if ((int)date('N') !== 1) { // If today is not Monday, calculate next one.
                        $integerversionnew = date('Ymd', strtotime('next Monday')) . '00';
                    }
                } else {
                    $integerversionnew = $date . '00'; // Apply $date also to major versions.
                }
                $decimalversionnew = '00'; // Majors always have the decimal reset to .00.
            }
        }

        // Replace the old version with the new version.
        if (strlen($decimalversionnew) === 1) {
            $decimalversionnew = '0'.$decimalversionnew;
        }
        $versionfile = str_replace($integerversioncurrent.'.'.$decimalversioncurrent, $integerversionnew.'.'.$decimalversionnew, $versionfile);
        // Replace the old build with the new build.
        $versionfile = str_replace('Build: '.$buildcurrent, 'Build: '.$buildnew, $versionfile);
        // Replace the old release with the new release if they've changed.
        if ($releasecurrent !== $releasenew) {
            $versionfile = str_replace($releasequote.$releasecurrent, $releasequote.$releasenew, $versionfile);
        }
        // Replace the old comment with the new one if they've changed
        if ($commentcurrent !== $commentnew) {
            $versionfile = str_replace($commentcurrent, $commentnew, $versionfile);
        }

        if (!$is19) {
            // Replace the branch value if need be.
            if ($branchcurrent !== $branchnew) {
                $versionfile = str_replace($branchquote.$branchcurrent.$branchquote, $branchquote.$branchnew.$branchquote, $versionfile);
            }
            // Replace the maturity value if need be.
            if ($maturitycurrent !== $maturitynew) {
                $versionfile = str_replace('= '.$maturitycurrent, '= '.$maturitynew, $versionfile);
            }
        }

        file_put_contents($path, $versionfile);

        return $releasenew;
    }

    function bump_dev_ensure_higher($versionint, $versiondec) {
        $today = date('Ymd');
        if ($versionint >= $today*100) {
            // Integer version is already past today * 100, increment version decimal part instead of integer part.
            $versiondec = (int)$versiondec + 1;
            $versiondec = (string)$versiondec;
        } else {
            $versionint = $today.'00';
            $versiondec = '00';
        }
        return array($versionint, $versiondec);
    }

    function branch_is_stable($branch, $isdevbranch) {
        return  (strpos($branch, '_STABLE') !== false && !$isdevbranch);
    }

    function validate_branch($branch) {
        if (!preg_match('#^(main|MOODLE_(\d+)_STABLE)$#', $branch, $matches)) {
            throw new Exception('Invalid branch given', __LINE__);
        }
        return true;
    }

    function validate_type($type) {
        $types = array('weekly', 'minor', 'major', 'beta', 'rc', 'on-demand', 'on-sync', 'back-to-dev');
        if (!in_array($type, $types)) {
            throw new Exception('Invalid type given.', __LINE__);
        }
        return true;
    }

    function validate_path($path) {
        if (file_exists($path) && is_readable($path)) {
            if (is_writable($path)) {
                return true;
            }
            throw new Exception('Path cannot be written to.', __LINE__);
        }
        throw new Exception('Invalid path given.', __LINE__);
    }

    function validate_version_file($contents, $branch) {
        $hasversion = strpos($contents, '$version ') !== false;
        $hasrelease = strpos($contents, '$release ') !== false;
        $hasbranch = strpos($contents, '$branch ') !== false;
        $hasmaturity = strpos($contents, '$maturity ') !== false;

        if ($hasversion && $hasrelease && $hasbranch && $hasmaturity) {
            return true;
        }
        if ($branch === 'MOODLE_19_STABLE' && $hasversion && $hasrelease) {
            return true;
        }
        throw new Exception('Invalid version file found.', __LINE__);
    }

    function get_option_from_options_array(array $options, $short, $long) {
        if (!isset($options[$short]) && !isset($options[$long])) {
            throw new Exception("Required option -$short|--$long must be provided.", __LINE__);
        }
        if ((isset($options[$short]) && is_array($options[$short])) ||
            (isset($options[$long]) && is_array($options[$long])) ||
            (isset($options[$short]) && isset($options[$long]))) {
            throw new Exception("Option -$short|--$long specified more than once.", __LINE__);
        }
        return (isset($options[$short])) ? $options[$short] : $options[$long];
    }

}
