#!/bin/bash

# This scripts propagates changes from official git repository to CVS

# master branch
cd gitmirror
git checkout master
git merge refs/remotes/origin/master
cd ..
/opt/local/bin/php git_to_cvs_mirror.php gitmirror/ cvsmoodle/

#19 branch
cd gitmirror
git checkout MOODLE_19_STABLE
git merge refs/remotes/origin/MOODLE_19_STABLE
cd ..
/opt/local/bin/php git_to_cvs_mirror.php gitmirror/ cvsmoodle19/

#reset to master again
cd gitmirror
git checkout master
cd ..
