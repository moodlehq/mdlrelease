#!/bin/bash 

# This scripts propagates changes from official integration Moodle git repository
# to main repostiory and mirrors
#
# Please note you need to have public ssh keys in all remotes except the origin!
#
# This script base dir
mydir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cd ${mydir}/gitmirror
git fetch origin

# Update public repositories
#  * moodle         - git://git.moodle.org/moodle.git
#  * github         - git://github.com/moodle/moodle.git
#  * gitorious      - git://github.com/moodle/moodle.git
git push --tags public refs/remotes/origin/master:refs/heads/master \
                        refs/remotes/origin/MOODLE_25_STABLE:refs/heads/MOODLE_25_STABLE \
                        refs/remotes/origin/MOODLE_24_STABLE:refs/heads/MOODLE_24_STABLE \
                        refs/remotes/origin/MOODLE_23_STABLE:refs/heads/MOODLE_23_STABLE \
                        refs/remotes/origin/MOODLE_19_STABLE:refs/heads/MOODLE_19_STABLE
# Discontinued 20130708 - refs/remotes/origin/MOODLE_22_STABLE:refs/heads/MOODLE_22_STABLE \
# Discontinued 20130114 - refs/remotes/origin/MOODLE_21_STABLE:refs/heads/MOODLE_21_STABLE \
# Discontinued 20120706 - refs/remotes/origin/MOODLE_20_STABLE:refs/heads/MOODLE_20_STABLE \
