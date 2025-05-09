<?php
/**
 * This script implements some useful svg manipulation tricks.
 *
 * Copied from theme/base/cli/svgtool.php 20/09/2013
 *
 * @package    theme_base
 * @subpackage cli
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Now get cli options.
list($options, $unrecognized) = cli_get_params(array('help'=>false, 'ie9fix'=>false, 'noaspectratio'=>false, 'path'=>dirname(__FILE__).'/gitmirror'),
    array('h'=>'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Unrecognised options:\n  {$unrecognized}\nPlease use --help option.");
}

$path = $options['path'];
if (!file_exists($path)) {
    cli_error("Invalid path $path");
}

$CFG = new stdClass;
$CFG->dirroot = $path;

// If necessary add files that should be ignored - such as in 3rd party plugins.
$blacklist = array(
        // We don't touch fontawesome ever.
        $CFG->dirroot . '/lib/fonts/fontawesome-webfont.svg',
        $CFG->dirroot . '/lib/fonts/font-awesome-4.7.0/fonts/fontawesome-webfont.svg',
);

if ($options['ie9fix']) {
    theme_base_recurse_svgs($path, '', 'theme_base_svgtool_ie9fix', $blacklist);

} else if ($options['noaspectratio']) {
    theme_base_recurse_svgs($path, '', 'theme_base_svgtool_noaspectratio', $blacklist);

} else {
    $help =
        "Some svg image tweaks for icon designers.

Options:

-h, --help            Print out this help
--ie9fix              Adds preserveAspectRatio=\"xMinYMid meet\" to every svg image
--noaspectratio       Removes preserveAspectRatio from svg files
--path=PATH           Path to directory or file to be converted, by default \$CFG->dirroot

Examples:
\$ php svgtool.php --ie9fix
\$ php svgtool.php --ie9fix --path=../../../pix
\$ php svgtool.php --noaspectratio
";

    echo $help;
    die;
}

exit(0);


function theme_base_svgtool_ie9fix($file) {
    global $CFG;

    if (strpos($file, $CFG->dirroot.DIRECTORY_SEPARATOR) === 0) {
        $relfile = substr($file, strlen($CFG->dirroot));
    } else {
        $relfile = $file;
    }

    $content = file_get_contents($file);

    if (!preg_match('/<svg\s[^>]*>/', $content, $matches)) {
        echo "  skipping $relfile (invalid format)\n";
        return;
    }
    $svg = $matches[0];
    if (strpos($svg, 'preserveAspectRatio') !== false) {
        return;
    }

    if (!is_writable($file)) {
        echo "  skipping $relfile (can not modify file)\n";
        return;
    }

    $newsvg = rtrim($svg, '>').' preserveAspectRatio="xMinYMid meet">';

    $content = str_replace($svg, $newsvg, $content);
    echo "converting $relfile\n";
    file_put_contents($file, $content);
}

function theme_base_svgtool_noaspectratio($file) {
    global $CFG;

    if (strpos($file, $CFG->dirroot.DIRECTORY_SEPARATOR) === 0) {
        $relfile = substr($file, strlen($CFG->dirroot));
    } else {
        $relfile = $file;
    }

    $content = file_get_contents($file);

    if (!preg_match('/<svg\s[^>]*>/', $content, $matches)) {
        echo "  skipping $relfile (invalid format)\n";
        return;
    }
    $svg = $matches[0];
    if (strpos($svg, 'preserveAspectRatio="xMinYMid meet"') === false) {
        return;
    }

    if (!is_writable($file)) {
        echo "  skipping $relfile (can not modify file)\n";
        return;
    }

    $newsvg = preg_replace('/ ?preserveAspectRatio="xMinYMid meet"/', '', $svg);

    $content = str_replace($svg, $newsvg, $content);
    echo "resetting $relfile\n";
    file_put_contents($file, $content);
}

function theme_base_recurse_svgs($base, $sub, $filecallback, $blacklist) {
    if (is_dir("$base/$sub")) {
        $items = new DirectoryIterator("$base/$sub");
        foreach ($items as $item) {
            if ($item->isDot()) {
                continue;
            }
            $file = $item->getFilename();
            theme_base_recurse_svgs("$base/$sub", $file, $filecallback, $blacklist);
        }
        unset($item);
        unset($items);
        return;

    } else if (is_file("$base/$sub")) {
        if (substr($sub, -4) !== '.svg') {
            return;
        }
        $file = realpath("$base/$sub");
        if (in_array($file, $blacklist)) {
            return;
        }
        $filecallback($file);
    }
}

/**
 * Returns cli script parameters.
 *
 * Copied from lib/clilib.php 20/09/2013
 *
 * @param array $longoptions array of --style options ex:('verbose'=>false)
 * @param array|null $shortmapping array describing mapping of short to long style options ex:('h'=>'help', 'v'=>'verbose')
 * @return array array of arrays, options, unrecognised as optionlongname=>value
 */
function cli_get_params(array $longoptions, ?array $shortmapping=null) {
    $shortmapping = (array)$shortmapping;
    $options      = array();
    $unrecognized = array();

    if (empty($_SERVER['argv'])) {
        // bad luck, we can continue in interactive mode ;-)
        return array($options, $unrecognized);
    }
    $rawoptions = $_SERVER['argv'];

    //remove anything after '--', options can not be there
    if (($key = array_search('--', $rawoptions)) !== false) {
        $rawoptions = array_slice($rawoptions, 0, $key);
    }

    //remove script
    unset($rawoptions[0]);
    foreach ($rawoptions as $raw) {
        if (substr($raw, 0, 2) === '--') {
            $value = substr($raw, 2);
            $parts = explode('=', $value);
            if (count($parts) == 1) {
                $key   = reset($parts);
                $value = true;
            } else {
                $key = array_shift($parts);
                $value = implode('=', $parts);
            }
            if (array_key_exists($key, $longoptions)) {
                $options[$key] = $value;
            } else {
                $unrecognized[] = $raw;
            }

        } else if (substr($raw, 0, 1) === '-') {
            $value = substr($raw, 1);
            $parts = explode('=', $value);
            if (count($parts) == 1) {
                $key   = reset($parts);
                $value = true;
            } else {
                $key = array_shift($parts);
                $value = implode('=', $parts);
            }
            if (array_key_exists($key, $shortmapping)) {
                $options[$shortmapping[$key]] = $value;
            } else {
                $unrecognized[] = $raw;
            }
        } else {
            $unrecognized[] = $raw;
            continue;
        }
    }
    //apply defaults
    foreach ($longoptions as $key=>$default) {
        if (!array_key_exists($key, $options)) {
            $options[$key] = $default;
        }
    }
    // finished
    return array($options, $unrecognized);
}

/**
 * Write error notification
 *
 * Copied from lib/clilib.php 20/09/2013
 *
 * @param $text
 * @return void
 */
function cli_problem($text) {
    fwrite(STDERR, $text."\n");
}

/**
 * Write to standard out and error with exit in error.
 *
 * Copied from lib/clilib.php 20/09/2013
 *
 * @param string $text
 * @param int $errorcode
 * @return void (does not return)
 */
function cli_error($text, $errorcode=1) {
    fwrite(STDERR, $text);
    fwrite(STDERR, "\n");
    die($errorcode);
}
