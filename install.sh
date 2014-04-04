#!/bin/bash

# This scripts prepares git checkout necessary for the prerelease and release.sh scripts
# Please note you need to have public ssh key in all the remotes below (github, gitorious, bitbucket)
# And also, can decide about the protocol (ssh, https) to be used against moodle main repositories.

protocol=${1:-ssh}
username=${2:-}

if [[ "${protocol}" != "ssh" ]] && [[ "${protocol}" != "https" ]]; then
    echo "incorrect protocol '${protocol}' used. Valid values: ssh (default) and https"
    exit 1
fi

if [[ -n "${username}" ]]; then
    username="${username}@"
fi

if [[ "${protocol}" == "ssh" ]]; then
    remotem="git@git.in.moodle.com:moodle/moodle.git"
    remotei="git@git.in.moodle.com:moodle/integration.git"
else
    remotem="https://${username}git.in.moodle.com/moodle/moodle.git"
    remotei="https://${username}git.in.moodle.com/moodle/integration.git"
fi

echo "About to clone integration repository using '${protocol}' with"
echo "remote url '${remotei}'"

read -r -p "Are you sure? [y/N] " response
response="$(tr [A-Z] [a-z] <<< "${response}")"
if [[ $response =~ ^(yes|y)$ ]]; then
    echo "Ok, proceeding"
else
    echo "Ok, cancelled"
    exit
fi

if [ -d gitmirror ]; then
    read -r -p "Directory gitmirror already exists. Do you agree to remove it? [y/N] " response
    response="$(tr [A-Z] [a-z] <<< "${response}")"
    if [[ $response =~ ^(yes|y)$ ]]; then
        rm -rf gitmirror && echo "Directory removed" || echo "Unable to remove directory gitmirror. Aborting"; exit
    else
        echo "Installation cancelled"
        exit
    fi
fi

git clone ${remotei} gitmirror
cd gitmirror

# Set a push URL for the origin (integration) remote repository.
git remote set-url --push origin ${remotei}

# Add the public repositories as a single remote to make pushing easy.
git remote add public ${remotem}
git remote set-url --add --push public ${remotem}
git remote set-url --add --push public git@github.com:moodle/moodle.git
git remote set-url --add --push public git@gitorious.org:moodle/moodle.git
git remote set-url --add --push public git@bitbucket.org:moodle/moodle.git

git branch --track MOODLE_26_STABLE refs/remotes/origin/MOODLE_26_STABLE
git branch --track MOODLE_25_STABLE refs/remotes/origin/MOODLE_25_STABLE
git branch --track MOODLE_24_STABLE refs/remotes/origin/MOODLE_24_STABLE
# Discontinued 20140113 - git branch --track MOODLE_23_STABLE refs/remotes/origin/MOODLE_23_STABLE
# Discontinued 20130708 - git branch --track MOODLE_22_STABLE refs/remotes/origin/MOODLE_22_STABLE
# Discontinued 20130114 - git branch --track MOODLE_21_STABLE refs/remotes/origin/MOODLE_21_STABLE
# Discontinued 20120706 - git branch --track MOODLE_20_STABLE refs/remotes/origin/MOODLE_20_STABLE
# Discontinued 20140113 - git branch --track MOODLE_19_STABLE refs/remotes/origin/MOODLE_19_STABLE
cd ..
