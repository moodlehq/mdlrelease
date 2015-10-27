<?php
// Adjust .travis.yml file given a branch, release type and path.

// Perth is the center of the world. Anything to object?
date_default_timezone_set('Australia/Perth');

try {
    $shortoptions = 'b:t:p:';
    $longoptions = array('branch:', 'type:', 'path:');

    $options = getopt($shortoptions, $longoptions);
    $branch = get_option_from_options_array($options, 'b', 'branch');
    $type = get_option_from_options_array($options, 't', 'type');
    $path = get_option_from_options_array($options, 'p', 'path');
    $path = rtrim($path, '/').'/.travis.yml';

    $message = adjust_travis($path, $branch, $type);
    $result = 0;
} catch (Exception $ex) {
    $message = $ex->getMessage();
    $result = $ex->getCode();
}
echo $message;
exit($result);

function adjust_travis($path, $branch, $type) {

    validate_branch($branch);
    validate_type($type);
    validate_path($path);

    $travisfile = file_get_contents($path);

    validate_travis_file($travisfile, $branch);

    // Replace the git fetch line
    $masterfetch = 'git fetch upstream master;';
    $newfetch = 'git fetch upstream ' . $branch . ';';
    if (!preg_match('#^ *' . $masterfetch . '$#m', $travisfile, $matches)) {
        throw new Exception('Could not find "' . $masterfetch . '" line.', __LINE__);
    }
    $travisfile = str_replace($masterfetch, $newfetch, $travisfile);

    file_put_contents($path, $travisfile);

    return $newfetch;
}

function validate_branch($branch) {
    if (!preg_match('#^MOODLE_(\d+)_STABLE$#', $branch, $matches)) {
        throw new Exception('Invalid branch given', __LINE__);
    }
    return true;
}

function validate_type($type) {
    $types = array('major');
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

function validate_travis_file($contents, $branch) {
    // Some random bits to look for.
    $hasmatrix = strpos($contents, 'matrix:') !== false;
    $hasallowfailures = strpos($contents, '    allow_failures:') !== false;
    $haslocalci = strpos($contents, 'moodlehq/moodle-local_ci') !== false;
    $hasphplint = strpos($contents, 'php_lint/php_lint.sh') !== false;

    if ($hasmatrix && $hasallowfailures && $haslocalci && $hasphplint) {
        return true;
    }
    throw new Exception('Invalid .travis.yml file found.', __LINE__);
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
