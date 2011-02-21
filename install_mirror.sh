#!/bin/bash

# This scripts prepares git checkout necessary for the gitmirror.sh script
# Please note you need to have public ssh key in all 3 remotes below
# and moodlerobot CVS too

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
git branch --track MOODLE_19_STABLE refs/remotes/origin/MOODLE_19_STABLE
git branch --track MOODLE_18_STABLE refs/remotes/origin/MOODLE_18_STABLE
git branch --track MOODLE_17_STABLE refs/remotes/origin/MOODLE_17_STABLE
git branch --track MOODLE_16_STABLE refs/remotes/origin/MOODLE_16_STABLE
cd ..

export CVS_RSH=ssh
cvs -z3 -d:ext:moodlerobot@cvs.moodle.org:/cvsroot/moodle co -P moodle

mv moodle cvsmoodle
cd cvsmoodle
cvs -q update -dP
cd ..

cp -R cvsmoodle cvsmoodle19
cd cvsmoodle19
cvs -q update -dP -r MOODLE_19_STABLE
cd ..

cp -R cvsmoodle cvsmoodle18
cd cvsmoodle18
cvs -q update -dP -r MOODLE_18_STABLE
cd ..
