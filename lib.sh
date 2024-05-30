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

# We need to check if nvm is available and load it, because prerelease requires it.
# First, check if can be loaded from NVM_DIR/nvm.sh, else, try to load it from HOME/.nvm/nvm.sh,
# with error if none is available.
if [ -r "${NVM_DIR}/nvm.sh" ]; then
    source "${NVM_DIR}/nvm.sh"
elif [ -r "${HOME}/.nvm/nvm.sh" ]; then
    source "${HOME}/.nvm/nvm.sh"
else
    echo "Unable to load nvm, please set NVM_DIR to the correct path."
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

# Given a UTC time in the HH:MM:SS format, returns the next unix seconds
# when it will be that UTC time.
function next_utc_time() {
    local time="${1}" # Time in HH:MM:SS format.
    local now         # Current time in seconds.
    local next        # To calculate the next time in seconds.
    now=$(LC_ALL=C date -u +%s)
    next=$(LC_ALL=C date -u -d "$(LC_ALL=C date -u -d @"${now}" +"%Y-%m-%d $time")" +%s)
    if [ "${now}" -gt "${next}" ]; then # If the time has already passed today.
        next=$(LC_ALL=C date -u -d "$(LC_ALL=C date -u -d @"${next}") +1 day" +%s)
    fi
    echo "${next}" # Return the next time in seconds.
}
