<?php
//
//Copyright 2011 Petr Skoda. All rights reserved.
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
 * Fix Moodle file and directory permissions
 *
 * @copyright 2011 Petr Skoda (http://skodak.org)
 * @license   Simplified BSD License
 */

if (isset($_SERVER['REMOTE_ADDR'])) {
    echo('Command line scripts can not be executed from the web interface!');
    exit(1);
}

//======= Get params and define global settings =======

if (empty($_SERVER['argc']) or $_SERVER['argc'] !== 2) {
    echo "Expected one parameter - git checkout location";
}

$sourcedir = $_SERVER['argv'][1];

define('VERBOSE', false);
define('PARSEGITIGNORE', true);

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

$sourcedir = realpath($sourcedir);

$gitignore    = array('.gitignore', '.cvsignore', '.settings', '.buildpath', '.project', '.idea', '.git', 'CVS', '.DS_Store');
/* List of moodle files that are supposed to be executable */

$executables = array(
    'filter/tex/mimetex.darwin',
    'filter/tex/mimetex.exe',
    'filter/tex/mimetex.freebsd',
    'filter/tex/mimetex.linux',
    'filter/tex/mimetex.linux.aarch64',
    'filter/algebra/algebra2tex.pl',
    '.grunt/upgradenotes.mjs',
    // filter/tex/mimetex.exe added to avoid problems running unit tests in Windows (MDL-47648)
    // do not include 'lib/editor/tinymce/extra/tools/download_langs.sh' here because it is used by devs only

);

// find out full names of the executables
foreach ($executables as $relname) {
    $realpath = realpath("$sourcedir/$relname");
    if ($realpath !== false) {
        $executables[$realpath] = $relname;
    } else {
        echo "Can not find real path for $relname in git repository\n";
        exit(1);
    }
    if (!file_exists($realpath)) {
        echo "Can not find executable file $realpath in git repository\n";
        exit(1);
    }
}

process_dir($sourcedir, $executables, $gitignore);

exit(0);


//======== Functions =======


function process_dir($source, array $executables, array $gitignore) {
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
    foreach($iterator as $file) {
        if ($file->isDot()) {
            // dots would lead to infinite loops
            continue;
        }
        $name = $file->getFilename();

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

        if ($file->isDir()) {
            verify_dir_permissions("$source/$name");
            process_dir("$source/$name", $executables, $gitignore);

        } else if ($file->isFile()) {
            verify_file_permissions("$source/$name", $executables);
        }
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

function verify_dir_permissions($dir) {
    if (!chmod($dir, 0755)) {
        echo "Can not change $dir permissions\n";
    }
}
function verify_file_permissions($file, $executables) {
    $file = realpath($file);
    $oldpermissions = fileperms($file);
    $oldpermissions = $oldpermissions & 0777; // we just want the basic permissions

    if (isset($executables[$file])) {
        if ($oldpermissions !== 0755) {
            #echo "0755: $file\n";
            if (!chmod($file, 0755)) {
                echo "Can not change $file permissions\n";
            }
        }

    } else {
        if ($oldpermissions !== 0644) {
            #echo "0644: $file\n";
            if (!chmod($file, 0644)) {
                echo "Can not change $file permissions\n";
            }
        }
    }
}
