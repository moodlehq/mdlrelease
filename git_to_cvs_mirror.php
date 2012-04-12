<?php
//
//Copyright 2010 Petr Skoda. All rights reserved.
//
//Redistribution and use in source and binary forms, with or without modification, are
//permitted provided that the following conditions are met:
//
//   1. Redistributions of source code must retain the above copyright notice, this list of
//      conditions and the following disclaimer.
//
//   2. Redistributions in binary form must reproduce the above copyright notice, this list
//      of conditions and the following disclaimer in the documentation and/or other materials
//      provided with the distribution.
//
//THIS SOFTWARE IS PROVIDED BY Petr Skoda ``AS IS'' AND ANY EXPRESS OR IMPLIED
//WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
//FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL Petr Skoda OR
//CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
//CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
//SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
//ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
//NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
//ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
//
//The views and conclusions contained in the software and documentation are those of the
//authors and should not be interpreted as representing official policies, either expressed
//or implied, of Petr Skoda.

/**
 * This script export a snapshot from git repo into read only CVS mirror.
 *
 * @copyright 2010 Petr Skoda (http://skodak.org)
 * @license   Simplified BSD License
 */

if (isset($_SERVER['REMOTE_ADDR'])) {
    echo('Command line scripts can not be executed from the web interface!');
    exit(1);
}

//======= Get params and define global settings =======

if (empty($_SERVER['argc']) or $_SERVER['argc'] !== 3) {
    echo "Expected two parameters - source and target";
}

$sourcedir = $_SERVER['argv'][1];
$targetdir = $_SERVER['argv'][2];

define('DIRPERMISSION', 02777);
define('VERBOSE', true);
define('PARSEGITIGNORE', true);
define('DRYRUN', false);
define('BINARYMODE', false);

//======== Execute the process =======


if (!is_dir($sourcedir)) {
    echo "Source directory $sourcedir does not exist\n";
    exit(1);
}
if (!is_dir("$sourcedir/.git")) {
    echo "Source directory $sourcedir is not git repository\n";
    exit(1);
}
if (!is_file("$sourcedir/config-dist.php")) {
    echo "Source directory $sourcedir is not moodle repository\n";
    exit(1);
}


if (!is_dir($targetdir)) {
    echo "Target directory $targetdir does not exist\n";
    exit(1);
}
if (!is_dir("$targetdir/CVS")) {
    echo "Target directory $targetdir is not CVS repository\n";
    exit(1);
}

if (!is_writable($targetdir)) {
    echo "Target directory $targetdir is not writable\n";
    exit(1);
}

$sourcedir = realpath($sourcedir);
$targetdir = realpath($targetdir);

$gitignore = array('.gitignore', '.cvsignore', '.settings', '.buildpath', '.project', '.idea', '.git', 'CVS', '.DS_Store', 'githash.php');

process_dir($sourcedir, $targetdir, $gitignore);

$HEAD = trim(file_get_contents("$sourcedir/.git/HEAD"));
$HEAD = str_replace('ref: ', '', $HEAD);

$hash = trim(file_get_contents("$sourcedir/.git/$HEAD"));

if ($hash and $hashfile = file_get_contents("$targetdir/githash.php")) {
    if (VERBOSE) {
        echo "Changing githash to $hash\n";
    }
    $hashfile = preg_replace("/githash\s*=\s*'[0-9a-f]+'/", "githash = '$hash'", $hashfile);
    file_put_contents("$targetdir/githash.php", $hashfile);
}

exit(0);


//======== Functions =======


function process_dir($source, $target, array $gitignore) {
    //normalize gitignore from parent
    foreach ($gitignore as $key=>$ignore) {
        if (strpos($ignore, '/') === false) {
            // keep all globs
            continue;
        } else {
            // detect if the ignore from parent belongs to this dir
            if (substr($ignore, 0, 1) !== '/') {
                // normalise
                $ignore = '/'.$ignore;
            }
            $parts = explode('/', $ignore);
            array_shift($parts);
            $current = array_shift($parts);
            if ($current === basename($source)) {
                // related to this dir - just remove the current dir part
                $gitignore[$key] = implode('/', $parts);
            } else {
                // some other subdir - ignore
                unset($gitignore[$key]);
            }
        }
    }

    // add gitignore from current dir
    if (PARSEGITIGNORE and file_exists("$source/.gitignore")) {
        $newgitignore = file("$source/.gitignore");
        foreach ($newgitignore as $ignore) {
            $ignore = trim($ignore);
            if ($ignore === '') {
                // ignore empty lines
            } else if (strpos($ignore, '/') === false) {
                // keep all globs
                if (!in_array($ignore, $gitignore, true)) {
                    $gitignore[] = $ignore;
                }
            } else {
                if (substr($ignore, 0, 1) !== '/') {
                    // normalize
                    $ignore = '/'.$ignore;
                }
                if (!in_array($ignore, $gitignore, true)) {
                    $gitignore[] = $ignore;
                }
            }
        }
    }

    $iterator = new DirectoryIterator($source);
    $processed = array();
    $addedfiles = array();
    foreach($iterator as $file) {
        if ($file->isDot()) {
            // dots would lead to infinite loops
            continue;
        }
        $name = $file->getFilename();

        // keep list of processed files, files not present here will be deleted from target
        $processed[$name] = $name;

        if ($file->isLink()) {
            // we do not support symbolic links!
            continue;
        }

        if (is_ignored($file, $gitignore)) {
            if (VERBOSE) {
                echo "   ignoring: $source/$name\n";
            }
            continue;
        }

        if (!file_exists("$target/CVS")) {
            // add CVS directory
            cvs_add_directory($target);
        }

        if ($file->isDir()) {
            // add to CVS before switching to different dir, it might fail there
            if ($addedfiles) {
                // add files to CVS
                cvs_add_files($target, $addedfiles);
            }
            $addedfiles = array();
            // recurse into subdirs
            process_dir("$source/$name", "$target/$name", $gitignore);

        } else if ($file->isFile()) {
            if (!file_exists("$target/$name")) {
                // add directories
                $addedfiles[] = $name;
            }
            update_file($file, $source, $target);
        }
    }
    // release file handles
    unset($file);
    unset($iterator);

    if ($addedfiles) {
        // add files to CVS
        cvs_add_files($target, $addedfiles);
    }

    // remove the deleted files from CVS
    $iterator = new DirectoryIterator($target);
    $deletefiles = array();
    foreach($iterator as $file) {
        if ($file->isDot()) {
            // dots would lead to infinite loops
            continue;
        }
        $name = $file->getFilename();

        if (isset($processed[$name])) {
            // keep files that are in source
            continue;
        }

        if (is_ignored($file, $gitignore)) {
            // ignore existing files
            // note: we always use gitignore from source, never target!
            continue;
        }

        if ($file->isDir()) {
            cvs_delete_directory("$target/$name");
        } else if ($file->isFile()) {
            $deletefiles[] = $name;
        }
    }
    if ($deletefiles) {
        // delete files from CVS
        cvs_delete_files($target, $deletefiles);
    }
    // release file handles
    unset($file);
    unset($iterator);
}

function is_ignored($file, $gitignore) {
    $name = $file->getFilename();

    foreach($gitignore as $ignore) {
        $pignore = preg_quote($ignore, '|');
        $pignore = str_replace('\?', '.', $pignore); // one char
        $pignore = str_replace('\*', '.*', $pignore); // any chars

        if (strpos($ignore, '/') === false) {
            // shell blob pattern - both dirs and files
            if (preg_match("|^$pignore$|", $name)) {
                return true;
            }
        } else {
            if (substr($ignore, 0, 1) !== '/') {
                // this should be already normalised, anyway.....
                $ignore = '/'.$ignore;
            }
            // file or directory depending on trailing /
            if (substr($ignore, -1) === '/') {
                // ignore directory
                if ($file->isDir() and preg_match("|^$pignore$|", "/$name/")) {
                    return true;
                }
            } else {
                // ignore file
                if ($file->isFile() and preg_match("|^$pignore$|", "/$name")) {
                    return true;
                }
            }
        }
    }

    return false;
}

function update_file($file, $source, $target) {
    $name = $file->getFilename();
    if (file_exists("$target/$name")) {
        $sc = get_file_content("$source/$name");
        $tc = get_file_content("$target/$name");
        $binary = is_file_binary("$source/$name");

        if ($sc === $tc) {
            // nothing to do
            return;
        }
        if (!BINARYMODE and !$binary) {
            // try alternatives
            if (fix_cvstags(fix_newlines($sc)) === fix_cvstags(fix_newlines($tc))) {
                // very similar, do not update
                return;
            }
        }
        if (VERBOSE) {
            echo "U: $target/$name\n";
        }
        if (!DRYRUN) {
            $content = (BINARYMODE or $binary) ? $sc : fix_cvstags(fix_newlines($sc));
            $result = file_put_contents("$target/$name", $content);
            if ($result === false) {
                echo("ERROR: can not overwrite target file:$target/$name\n");
                exit(1);
            }
        }

    } else {
        if (VERBOSE) {
            echo "+: $target/$name\n";
        }
        if (!DRYRUN) {
            $sc = get_file_content("$source/$name");
            $binary = is_file_binary("$source/$name");
            $content = (BINARYMODE or $binary) ? $sc : fix_cvstags(fix_newlines($sc));
            $result = file_put_contents("$target/$name", $content);
            if ($result === false) {
                echo("ERROR: can not create target file:$target/$name\n");
                exit(1);
            }
        }
    }
}

function is_file_binary($filepath) {
    //TODO: make this list more configurable
    $text = array('php', 'html', 'htm', 'txt', 'inc', 'sql', 'js', 'css', 'htaccess', 'moodle', '', 'xsd', 'xml', 'scmt');

    $pathinfo = pathinfo($filepath);
    $extension = (isset($pathinfo['extension']) ? strtolower($pathinfo['extension']) : '');

    if ($filepath == 'lib/dml/tests/fixtures/randombinary') {
        return true;
    }


    if (in_array($extension, $text)) {
        return false;
    }

    return true;
}

function fix_newlines($content) {
    $content = str_replace("\r\n", "\n", $content); // win line endings
    $content = str_replace("\r", "\n", $content);   // remaining mac line endings
    return $content;
}

function fix_cvstags($content) {
    $tags = array('Id', 'RCSfile', 'Date', 'Revision', 'Author');
    foreach ($tags as $tag) {
        $content = preg_replace('|\$'.$tag.':[^\$]*\$|i', '\$'.$tag.'\$', $content);
    }
    return $content;
}

function get_file_content($filepath) {
    if (!file_exists($filepath)) {
        return null;
    }

    $content = file_get_contents($filepath);
    if ($content === false) {
        echo("ERROR: can not read file:$filepath\n");
        exit(1);
    }

    return $content;
}

//=== CVS specific mess ====

function cvs_add_files($target, $files) {
    $names = array('-kb'=>array(), '-ko'=>array());
    foreach ($files as $name) {
        if (is_file_binary($name)) {
            $names['-kb'][] = $name;
        } else {
            $names['-ko'][] = $name;
        }
    }

    foreach ($names as $flag=>$names) {
        $add = array();
        foreach($names as $name) {
            $add[] = '"'.escapeshellcmd($name).'"';
        }

        if ($add) {
            $addfiles = implode(' ', $add);

            if (VERBOSE) {
                if (DRYRUN) {
                    echo "!  ";
                } else {
                    echo "   ";
                }
                echo "cvs add $flag $addfiles\n";
            }

            if (!DRYRUN) {
                $cmd = 'cd "'.escapeshellcmd($target).'"; cvs add '.$flag.' '.$addfiles.' 2>&1';
                $output = null;
                $return = null;
                exec($cmd, $output, $return);
                if ($return != 0) {
                    $output = implode("\n", $output);
                    if (strpos($output, 'has already been entered') !== false) {
                        //nothing major - ignore this problem
                        echo "WARNING: CVS add problem for files: $addfiles:\n";
                        echo $output;
                        echo "\n";
                    } else {
                        echo "CVS add error for files: $addfiles:\n";
                        echo $output;
                        echo "\n";
                        exit(1);
                    }
                }
            }
        }
    }
}

function cvs_add_directory($target) {

    clearstatcache(false, $target); // clear PHP caches
    if (!file_exists($target)) {
        if (!DRYRUN) {
            umask(0);
            mkdir($target, DIRPERMISSION, true);
        }
    }

    clearstatcache(false, "$target/CVS"); // clear PHP caches
    if (file_exists("$target/CVS")) {
        // dir already tracked in CVS
        return;
    }

    if (!DRYRUN) {
        $cmd = 'cd "'.escapeshellcmd(dirname($target)).'"; cvs add "'.escapeshellcmd(basename($target)).'" 2>&1';
        $output = null;
        $return = null;
        exec($cmd, $output, $return);
        if ($return != 0) {
            $output = implode("\n", $output);
            if (preg_match('/there is a version in .+ already/', $output)) {
                //nothing major - ignore this problem; this is weird anyway....
                echo "WARNING: CVS add problem for directory $target:\n";
                echo $output;
                echo "\n";
            } else {
                echo "CVS add error for directory $target:\n";
                echo $output;
                echo "\n";
                exit(1);
            }
        }
    }
}

function cvs_delete_files($target, $names) {
    $remove = array();
    foreach($names as $name) {
        $remove[] = '"'.escapeshellcmd($name).'"';
        if (VERBOSE) {
            echo "-: $target/$name\n";
        }
        if (!DRYRUN) {
            unlink("$target/$name");
        }
    }

    if ($remove) {
        $removefiles = implode(' ', $remove);

        if (VERBOSE) {
            if (DRYRUN) {
                echo "!  ";
            } else {
                echo "   ";
            }
            echo "cvs remove $removefiles\n";
        }

        if (!DRYRUN) {
            $cmd = 'cd "'.escapeshellcmd($target).'"; cvs remove '.$removefiles.' 2>&1';
            $output = null;
            $return = null;
            exec($cmd, $output, $return);
            if ($return != 0) {
                echo "CVS remove error for files: $removefiles:\n";
                echo implode("\n", $output);
                echo "\n";
                exit(1);
            }
        }
    }
}

function cvs_delete_directory($target) {
    // remove all dir files from CVS
    $iterator = new DirectoryIterator($target);
    $deletefiles = array();
    foreach($iterator as $file) {
        if ($file->isDot()) {
            // dots would lead to infinite loops
            continue;
        }
        $name = $file->getFilename();

        if ($name === 'CVS') {
            // we must not delete CVS meta data
            // empty dirs are purged automatically
            continue;
        }

        if ($file->isDir()) {
            cvs_delete_directory("$target/$name");
        } else if ($file->isFile()) {
            $deletefiles[] = $name;
        }
    }
    if ($deletefiles) {
        // delete files from CVS
        cvs_delete_files($target, $deletefiles);
    }
    // release file handles
    unset($file);
    unset($iterator);
}
