#!/bin/bash
# Library of functions and variables shared by various scripts.
# Note that this needs to be sourced to work correctly.

# Include config to get access to branch information.
if [ -f "$(dirname "${0}")"/config.sh ]; then
    source "$(dirname "${0}")"/config.sh
else
    echo "Unable to include config.sh"
    exit 1
fi

# A few colours for output.
# Reset to normal.
N="$(tput sgr0)"
# Red.
R="$(tput setaf 1)"
# Green.
G="$(tput setaf 2)"
# Yellow.
Y="$(tput setaf 3)"
# Cyan.
C="$(tput setaf 6)"
# Bold.
bold="$(tput bold)"
# Normal.
normal="$(tput sgr0)"

# The base dir
mydir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

git_last_commit_hash() {
    git log --pretty=format:'%H' -n 1
    return 0
}

# To output anything from the scripts (content and, optionally, no line break).
output() {
    if $_verbose; then
        if [ -n "${2}" ]; then
            echo -n "${1}"
        else
            echo "${1}"
        fi
    fi
}
