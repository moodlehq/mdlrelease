#!/bin/bash

# This script updates things as required.

# Reset to normal.
N="$(tput setaf 9)"
# Red.
R="$(tput setaf 1)"
# Green.
G="$(tput setaf 2)"
# Yellow.
Y="$(tput setaf 3)"
# Cyan.
C="$(tput setaf 6)"

startdir=`pwd`
mydir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
if [[ ! -d ${mydir}/gitmirror ]] ; then
    output "Directory ${mydir}/gitmirror not found. You may need to create it with install.sh"
    exit 1
fi
cd ${mydir}/gitmirror

# Set a push URL for the origin (integration) remote repository.
set_origin_push_url() {
    echo "${C}Checking the origin remote has a push URL set.${N}"
    # Check origin exists. If it doesn't...
    git ls-remote origin >/dev/null 2>&1
    if [[ $? -ge 1 ]] ; then
        echo "${R}FATAL ERROR: the origin remote doesn't exist... what have you done?!${N}"
        exit 1
    fi
    details=`git remote show -n origin`
    if [[ "$details" =~ "@git.moodle.org:/git/integration.git" ]] ; then
        # Nothing to do here.
        echo "Origin push URL already set"
    else
        # Push URL hasn't been set - ask the user what there username is and add it.
        read -p "What is your git.moodle.org username? " username
        git remote set-url --push origin $username@git.moodle.org:/git/integration.git
    fi
}

# Remove a remote repository
# Argument 1: The remote to remove
remove_remote() {
    git ls-remote "$1" >/dev/null 2>&1
    if [[ $? -eq 0 ]] ; then
        echo "${C}Removing remote repository: $1.${N}"
        # The remote exists, remove it!
        git remote remove "$1"
    fi
}

# Check the public repository exists and if it doesn't add it.
add_public_remote() {
    git ls-remote public >/dev/null 2>&1
    if [[ $? -ge 1 ]] ; then
        echo "${C}Adding the public remote repository.${N}"
        read -p "What is your git.moodle.org username? " username
        # Add the public repositories as a single remote to make pushing easy.
        git remote add public git://git.moodle.org/moodle.git
        git remote set-url --add --push public $username@git.moodle.org:/git/moodle.git
        git remote set-url --add --push public git@github.com:moodle/moodle.git
        git remote set-url --add --push public git@gitorious.org:moodle/moodle.git
        git remote set-url --add --push public git@bitbucket.org:moodle/moodle.git
    fi
}


# Set the origin push URL if required.
set_origin_push_url

# Add the public repository if required.
add_public_remote

# Remove the only single instance public repositories.
remove_remote "moodle"
remove_remote "github"
remove_remote "gitorious"

echo "${C}Updating and cleaning.${N}"
#git fetch --all --prune
#git gc --aggressive

cd ${startdir}
