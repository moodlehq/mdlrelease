<?php

// Perth is the center of the world. Anything to object?
date_default_timezone_set('Australia/Perth');

require_once(__DIR__ . '/src/helper.php');

$helper = new \MoodleHQ\MoodleRelease\Helper();

// We need the branch and the bump type (weekly. minor, major)
try {
    $shortoptions = 'b:t:p:r:d:i:';
    $longoptions = array('branch:', 'type:', 'path:', 'rc:', 'date:', 'isdevbranch:');

    $options = getopt($shortoptions, $longoptions);
    $branch = $helper->get_option_from_options_array($options, 'b', 'branch');
    $type = $helper->get_option_from_options_array($options, 't', 'type');
    $path = $helper->get_option_from_options_array($options, 'p', 'path');
    $rc = $helper->get_option_from_options_array($options, 'r', 'rc');
    $date = $helper->get_option_from_options_array($options, 'd', 'date');
    $isdevbranch = (bool)$helper->get_option_from_options_array($options, 'i', 'isdevbranch');
    $path = rtrim($path, '/').'/version.php';

    $release = $helper->bump_version($path, $branch, $type, $rc, $date, $isdevbranch);
    $result = 0;
} catch (Exception $ex) {
    $release = $ex->getMessage();
    $result = $ex->getCode();
}
echo $release;
exit($result);
