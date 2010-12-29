#!/bin/bash 

# This scripts propagates changes from official integration Moodle git repository
# to main repostiory and mirrors
#
# Please note you need to have public ssh keys in all remotes except hte origin!

cd gitmirror
git fetch origin

git push --tags moodle refs/remotes/origin/master:master
git push --tags moodle refs/remotes/origin/MOODLE_19_STABLE:MOODLE_19_STABLE
git push --tags moodle refs/remotes/origin/MOODLE_18_STABLE:MOODLE_18_STABLE
git push --tags moodle refs/remotes/origin/MOODLE_17_STABLE:MOODLE_17_STABLE
git push --tags moodle refs/remotes/origin/MOODLE_16_STABLE:MOODLE_16_STABLE

git push --tags github refs/remotes/origin/master:master
git push --tags github refs/remotes/origin/MOODLE_19_STABLE:MOODLE_19_STABLE
git push --tags github refs/remotes/origin/MOODLE_18_STABLE:MOODLE_18_STABLE
git push --tags github refs/remotes/origin/MOODLE_17_STABLE:MOODLE_17_STABLE
git push --tags github refs/remotes/origin/MOODLE_16_STABLE:MOODLE_16_STABLE

git push --tags gitorious refs/remotes/origin/master:master
git push --tags gitorious refs/remotes/origin/MOODLE_19_STABLE:MOODLE_19_STABLE
git push --tags gitorious refs/remotes/origin/MOODLE_18_STABLE:MOODLE_18_STABLE
git push --tags gitorious refs/remotes/origin/MOODLE_17_STABLE:MOODLE_17_STABLE
git push --tags gitorious refs/remotes/origin/MOODLE_16_STABLE:MOODLE_16_STABLE
