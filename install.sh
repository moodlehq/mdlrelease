#!/bin/bash

# This scripts prepares git checkout necessary for the prerelease and release.sh scripts
# Please note you need to have public ssh key in all 3 remotes below

username=$1

if [ -z "$username" ]
then
    echo "missing parameter: moodle.git username"
    exit
fi

git clone git://git.moodle.org/integration.git gitmirror
cd gitmirror

# Set a push URL for the origin (integration) remote repository.
git remote set-url --push origin $username@git.moodle.org:/git/integration.git

# Add the public repositories as a single remote to make pushing easy.
git remote add public git://git.moodle.org/moodle.git
git remote set-url --add --push public $username@git.moodle.org:/git/moodle.git
git remote set-url --add --push public git@github.com:moodle/moodle.git
git remote set-url --add --push public git@gitorious.org:moodle/moodle.git

git branch --track MOODLE_25_STABLE refs/remotes/origin/MOODLE_25_STABLE
git branch --track MOODLE_24_STABLE refs/remotes/origin/MOODLE_24_STABLE
git branch --track MOODLE_23_STABLE refs/remotes/origin/MOODLE_23_STABLE
# Discontinued 20130708 - git branch --track MOODLE_22_STABLE refs/remotes/origin/MOODLE_22_STABLE
# Discontinued 20130114 - git branch --track MOODLE_21_STABLE refs/remotes/origin/MOODLE_21_STABLE
# Discontinued 20120706 - git branch --track MOODLE_20_STABLE refs/remotes/origin/MOODLE_20_STABLE
git branch --track MOODLE_19_STABLE refs/remotes/origin/MOODLE_19_STABLE
cd ..
