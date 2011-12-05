#!/bin/bash

# This scripts propagates changes from official git repository to CVS

# master branch
cd gitmirror
git checkout master
git merge refs/remotes/origin/master
cd ..
/opt/local/bin/php git_to_cvs_mirror.php gitmirror/ cvsmoodle/

#22 branch
cd gitmirror
git checkout MOODLE_22_STABLE
git merge refs/remotes/origin/MOODLE_22_STABLE
cd ..
/opt/local/bin/php git_to_cvs_mirror.php gitmirror/ cvsmoodle22/

#21 branch
cd gitmirror
git checkout MOODLE_21_STABLE
git merge refs/remotes/origin/MOODLE_21_STABLE
cd ..
/opt/local/bin/php git_to_cvs_mirror.php gitmirror/ cvsmoodle21/

#20 branch
cd gitmirror
git checkout MOODLE_20_STABLE
git merge refs/remotes/origin/MOODLE_20_STABLE
cd ..
/opt/local/bin/php git_to_cvs_mirror.php gitmirror/ cvsmoodle20/

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
