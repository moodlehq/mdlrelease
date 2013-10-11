#!/bin/bash

# This scripts prepares git checkout necessary for the prerelease and release.sh scripts
# Please note you need to have public ssh key in all 3 remotes below

username=$1

if [ -z "$username" ]
then
    echo "This script takes one arg - your git.moodle.org username"
    exit
fi

cd gitmirror

# Set a push URL for the origin (integration) remote repository.
git remote set-url --push origin $username@git.moodle.org:/git/integration.git

# Add the public repositories as a single remote to make pushing easy.
git remote add public git://git.moodle.org/moodle.git
git remote set-url --add --push public $username@git.moodle.org:/git/moodle.git
git remote set-url --add --push public git@github.com:moodle/moodle.git
git remote set-url --add --push public git@gitorious.org:moodle/moodle.git

# Remove the only single instance public repositories.
git remote remove moodle
git remote remove github
git remote remove gitorious

git fetch --all --prune
git gc --aggressive

cd ..