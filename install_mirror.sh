#!/bin/bash

# This scripts prepares git checkout necessary for the gitmirror.sh script
# Please note you need to have public ssh key in all 3 remotes below

username=$1

if [ -z "$username" ]
then
    echo "missing parameter: moodle.git username"
    exit
fi

git clone git://git.moodle.org/integration.git gitmirror
cd gitmirror
git remote add moodle $username@git.moodle.org:/git/moodle.git
git remote add github git@github.com:moodle/moodle.git
git remote add gitorious git@gitorious.org:moodle/moodle.git
git branch --track MOODLE_25_STABLE refs/remotes/origin/MOODLE_25_STABLE
git branch --track MOODLE_24_STABLE refs/remotes/origin/MOODLE_24_STABLE
git branch --track MOODLE_23_STABLE refs/remotes/origin/MOODLE_23_STABLE
git branch --track MOODLE_22_STABLE refs/remotes/origin/MOODLE_22_STABLE
# Discontinued 20130114 - git branch --track MOODLE_21_STABLE refs/remotes/origin/MOODLE_21_STABLE
# Discontinued 20120706 - git branch --track MOODLE_20_STABLE refs/remotes/origin/MOODLE_20_STABLE
git branch --track MOODLE_19_STABLE refs/remotes/origin/MOODLE_19_STABLE
cd ..
