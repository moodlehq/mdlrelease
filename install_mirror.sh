#!/bin/bash 

# This scripts prepares git checkout necessary for the gitmirror.sh script
# Please note you need to have public ssh key in all 3 remotes bellow
# and moodlerobot CVS too

# create dir, it most probably already exists and contains this script, but anyway
git clone git://git.moodle.org/integration.git gitmirror
cd gitmirror
git remote add moodle skodak@git.moodle.com:/git/moodle.git
git remote add github git@github.com:moodle/moodle.git
git remote add gitorious git@gitorious.org:moodle/moodle.git
cd ..

export CVS_RSH=ssh
cvs -z3 -d:ext:moodlerobot@cvs.moodle.org:/cvsroot/moodle co -P moodle

mv moodle cvsmoodle
cd cvsmoodle
cvs update -dP
cd ..

cp -R cvsmoodle cvsmoodle19
cd cvsmoodle19
cvs update -dP -r MOODLE_19_STABLE
cd ..

cp -R cvsmoodle cvsmoodle18
cd cvsmoodle18
cvs update -dP -r MOODLE_18_STABLE
cd ..

