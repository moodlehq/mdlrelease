<?php

// Perth is the center of the world. Anything to object?
date_default_timezone_set('Australia/Perth');

// We need the branch and the bump type (weekly. minor, major)
try {
    $shortoptions = 'b:t:p:r:d:i:';
    $longoptions = array('branch:', 'type:', 'path:', 'rc:', 'date:', 'isdevbranch:');

    $options = getopt($shortoptions, $longoptions);
    $branch = get_option_from_options_array($options, 'b', 'branch');
    $type = get_option_from_options_array($options, 't', 'type');
    $path = get_option_from_options_array($options, 'p', 'path');
    $rc = get_option_from_options_array($options, 'r', 'rc');
    $date = get_option_from_options_array($options, 'd', 'date');
    $isdevbranch = (bool)get_option_from_options_array($options, 'i', 'isdevbranch');
    $path = rtrim($path, '/').'/version.php';

    $release = bump_version($path, $branch, $type, $rc, $date, $isdevbranch);
    $result = 0;
} catch (Exception $ex) {
    $release = $ex->getMessage();
    $result = $ex->getCode();
}
echo $release;
exit($result);



function bump_version($path, $branch, $type, $rc, $date, $isdevbranch) {

    validate_branch($branch);
    validate_type($type);
    validate_path($path);

    $versionfile = file_get_contents($path);

    validate_version_file($versionfile, $branch);

    $is19 = ($branch === 'MOODLE_19_STABLE');
    $isstable = branch_is_stable($branch, $isdevbranch);
    $today = date('Ymd');

    $versionmajorcurrent = null;
    $versionminorcurrent = null;
    $commentcurrent = null;
    $releasecurrent = null;
    $buildcurrent = null;
    $branchcurrent = null;
    $maturitycurrent = null;

    $branchquote = null;
    $releasequote = null;

    $versionmajornew = null;
    $versionminornew = null;
    $commentnew = null;
    $releasenew = null;
    $buildnew = null;
    $branchnew = null;
    $maturitynew = null;

    if (!preg_match('#^ *\$version *= *(?P<major>\d{10})\.(?P<minor>\d{2})\d?[^\/]*(?P<comment>/[^\n]*)#m', $versionfile, $matches)) {
        throw new Exception('Could not determine version.', __LINE__);
    }
    $versionmajornew = $versionmajorcurrent = $matches['major'];
    $versionminornew = $versionminorcurrent = $matches['minor'];
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
            $versionminornew++;
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
            $versionmajornew = (int)$versionmajornew + 1;
            $versionmajornew = (string)$versionmajornew;
            $versionminornew = '00';
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
                list($versionmajornew, $versionminornew) = bump_dev_ensure_higher($versionmajornew, $versionminornew);
            } else if (strpos($releasecurrent, 'dev') === false) {
                // Must be immediately after a major release. Bump the release version and set maturity to Alpha.
                $releasenew = (float)$releasenew + 0.1;
                $releasenew = (string)$releasenew.'dev';
                $maturitynew = 'MATURITY_ALPHA';
            }
            list($versionmajornew, $versionminornew) = bump_dev_ensure_higher($versionmajornew, $versionminornew);
        } else if ($type === 'beta') {
            $releasenew = preg_replace('#^(\d+.\d+) *(dev|beta)\+?#', '$1', $releasenew);
            $branchnew = $branchcurrent; // Branch doesn't change in beta releases ever.
            $releasenew .= 'beta';
            list($versionmajornew, $versionminornew) = bump_dev_ensure_higher($versionmajornew, $versionminornew);
            $maturitynew = 'MATURITY_BETA';
        } else if ($type === 'rc') {
            $releasenew = preg_replace('#^(\d+.\d+) *(dev|beta|rc\d)\+?#', '$1', $releasenew);
            $branchnew = $branchcurrent; // Branch doesn't change in rc releases ever.
            $releasenew .= 'rc'.$rc;
            list($versionmajornew, $versionminornew) = bump_dev_ensure_higher($versionmajornew, $versionminornew);
            $maturitynew = 'MATURITY_RC';
        } else if ($type === 'on-demand') {
            // Add the + if missing (normally applies to post betas & rcs only,
            // but it's not wrong to generalize it to any on-demand).
            if (strpos($releasenew, '+') === false) {
                // Add the +
                $releasenew .= '+';
            }
            list($versionmajornew, $versionminornew) = bump_dev_ensure_higher($versionmajornew, $versionminornew);
        } else if ($type === 'on-sync') {
            $versionminornew++;
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
            list($versionmajornew, $versionminornew) = bump_dev_ensure_higher($versionmajornew, $versionminornew);
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
                    $versionmajornew = date('Ymd', strtotime('next Monday')) . '00';
                }
            } else {
                $versionmajornew = $date . '00'; // Apply $date also to major versions.
            }
            $versionminornew = '00'; // Majors always have the decimal reset to .00.
        }
    }

    // Replace the old version with the new version.
    if (strlen($versionminornew) === 1) {
        $versionminornew = '0'.$versionminornew;
    }
    $versionfile = str_replace($versionmajorcurrent.'.'.$versionminorcurrent, $versionmajornew.'.'.$versionminornew, $versionfile);
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

function bump_dev_ensure_higher($major, $minor) {
    $today = date('Ymd');
    if ($major >= $today*100) {
        // Version is already past today * 100, increment minor version instead of major version.
        $minor = (int)$minor + 1;
        $minor = (string)$minor;
    } else {
        $major = $today.'00';
        $minor = '00';
    }
    return array($major, $minor);
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
