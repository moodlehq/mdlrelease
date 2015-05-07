#!/bin/bash

# This scripts prepares git checkout necessary for the prerelease and release.sh scripts
# Please note you need to have public ssh key in all the remotes below (github,  bitbucket)
# And also, can decide about the protocol (ssh, https) to be used against moodle main repositories.

# Include config to get access to branch information.
if [ -f $(dirname $0)/config.sh ]; then
    source $(dirname $0)/config.sh
else
    echo "Unable to include config.sh"
    exit 1
fi

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
if [[ $? -ge 1 ]] ; then
    # Drat it failed to clone. I bet you are a remote worker and either forgot to connect to the VPN or forgot to
    # specify https as the protocol.
    # Either way the gitmirror directory doesn't exist, we need to exit before we screw up your mdlrelease checkout.
    echo "Failed to clone the Moodle repository. Check the protocol, ${protocol} used. ($?)"
    exit 1
fi

cd gitmirror

# Set a push URL for the origin (integration) remote repository.
git remote set-url --push origin ${remotei}

# Add the public repositories as a single remote to make pushing easy.
git remote add public ${remotem}
git remote set-url --add --push public ${remotem}
git remote set-url --add --push public git@github.com:moodle/moodle.git
git remote set-url --add --push public git@bitbucket.org:moodle/moodle.git

# Create stable branches with the upstream set.
for branch in ${STABLEBRANCHES[@]}; do
    git branch --track ${branch} refs/remotes/origin/${branch}
done

# Create security branches with the upstream set.
for branch in ${SECURITYBRANCHES[@]}; do
    git branch --track ${branch} refs/remotes/origin/${branch}
done

cd ..
exit 0
