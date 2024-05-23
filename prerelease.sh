#!/bin/bash
# This script performs required pre-release processing.

# Include lib.sh to get access to shared stuff..
if [ -f "$(dirname "${0}")"/lib.sh ]; then
    source "$(dirname "${0}")"/lib.sh
else
    echo "Unable to include lib.sh"
    exit 1
fi

# The branches to push to integration.
integrationpush=""

# Command line arguments
_pushup=false # Push to integration when complete
_verbose=true # Make lots of noise.
_type='weekly' # The type of release we are making.
_forcebump=true # If true we make the bump regardless
_onlybranch='' # Gets set to a single branch if we only want one.
_reset=false # To discard any local change not available @ integration.git
_show=false # To show all the changes performed locally, comparing with integration.git
_date='' # To enforce any build date at any moment
_rc=0 # Sets the release candidate version
_types=("weekly" "minor" "major" "beta" "rc" "on-demand" "on-sync" "back-to-dev"); # All types.
_reltypes=("major" "beta" "rc" "on-demand" "on-sync" "back-to-dev"); # Major-release related types, always on a dev branch.
_nocreate=false
localbuffer=""

# Try to observe the "dev branches first, then stables from older to newer" rule.
# We don't make a weekly release of the security only branch any more. It is however still released during a minor release.
weeklybranches=( "${DEVBRANCHES[@]}" "${STABLEBRANCHES[@]}" );
minorbranches=( "${SECURITYBRANCHES[@]}" "${STABLEBRANCHES[@]}" );
# For on-demand, beta, rc, major, on-sync, back-to-dev, the target are always development branches.
# (we'll be reducing this later to the best dev branch, unless a explicity --branch is passed).
devbranches=("${DEVBRANCHES[@]}");

# Prepare an all branches array.
OLDIFS="$IFS"
IFS=$'\n'
# TODO: Remove master from the list once we delete it.
allbranches=($(for b in master "${DEVBRANCHES[@]}" "${STABLEBRANCHES[@]}" "${SECURITYBRANCHES[@]}" ; do echo "$b" ; done | sort -du))
IFS="$OLDIFS"

in_array() {
    for e in "${@:2}"; do [[ "$e" == "$1" ]] && return 0; done
    return 1
}

git_staged_changes() {
    if ! git diff-index --quiet --cached --ignore-submodules  HEAD -- ; then
        return 0
    fi
    return 1
}

git_unstaged_changes() {
    if ! git diff-files --quiet --ignore-submodules ; then
        return 0
    fi
    return 1
}

git_last_commit_hash() {
    echo `git log --pretty=format:'%H' -n 1`
    return 0
}

all_clean() {
    er=false
    if git_unstaged_changes ; then
        echo "${R}There are unstaged changes in the gitmirror repository.${N}"
        er=true
    fi

    if git_staged_changes ; then
        echo "${R}There are uncommit changes in the gitmirror repository.${N}"
        er=true
    fi

    if $er ; then
        exit 1
    fi
}

# Argument 1: branch
# Argument 2: type
# Argument 3: pwd
# Argument 4: rc
# Argument 5: date
# Argument 6: isdevbranch
bump_version() {
    local release=`php ${mydir}/bumpversions.php -b "$1" -t "$2" -p "$3" -r "$4" -d "$5" -i "$6"`
    local outcome=$?
    local return=0
    local weekly=false
    local ondemand=false
    local onsync=false
    local backtodev=false

    if [ "$2" == "weekly" ] ; then
        # It's a weekly release... easy!
        weekly=true
    elif [ "$2" == "on-demand" ] ; then
        # It's a on-demand release... easy too!
        ondemand=true
    elif [ "$2" == "on-sync" ] ; then
        # It's a on-sync release... easy too!
        onsync=true
    elif [ "$2" ==  "back-to-dev" ] ; then
        # Just returning main to dev after major
        backtodev=true
    elif [ "$1" == "main" ] && [ "$2" == "minor" ] ; then
        # It's the main branch and a minor release - main just gets a weekly.
        weekly=true
    fi

    if (( outcome > 0 ))  ; then
        output "  - ${R}Failed to bump version file [$outcome].${N}"
        _pushup=false
    else
        git add version.php
        if git_staged_changes ; then
            if $weekly ; then
                git commit --quiet -m "weekly release $release"
            elif $ondemand ; then
                git commit --quiet -m "on-demand release $release"
            elif $onsync ; then
                git commit --quiet -m "weekly on-sync release $release"
            elif $backtodev ; then
                git commit --quiet -m "weekly back-to-dev release $release"
            else
                git commit --quiet -m "Moodle release $release"
                local tagversion=`get_release_tag_version "$release"` # v2.6.0
                local tagannotation=`get_release_tag_annotation "$release"` # MOODLE_26
                local taghash=`git_last_commit_hash` # The full git commit hash for the last commit on the branch

                if [ "$1" == "main" ] && [ "$2" == "major" ] ; then
                    # Exciting!

                    # Calculate new branch name.
                    local newbranch=`get_new_stable_branch "$release"` # MOODLE_XX_STABLE

                    # Delete the last commit (version.php changes one). May need other stuff before.
                    git reset --quiet --hard HEAD^1

                    # Originally, here it is where we used to automatically apply for travis changes
                    # when a new branch was going to be created. If, in the future we need a place
                    # to automate any other change before creating a new branch... this is the place.
                    # (between the git reset above and the git cherry-pick following this comment).
                    #
                    # For a valid example about how to apply for automated changes before branching,
                    # you can look for "travis" occurrences in a checkout of 67ff731 (last commit
                    # having that automatism enabled).

                    # Add back the version.php commit and annotate it (tagging point).
                    git cherry-pick $taghash > /dev/null
                    taghash=`git_last_commit_hash`

                    # Now we can proceed to branch safely.
                    output "  - Creating new stable branch $newbranch"
                    git branch -f "$newbranch" main # create from (or reset to) main

                    integrationpush="$integrationpush $newbranch"
                fi

                # Calculate git tags.
                if $_pushup ; then
                    #git tag -a "$tagversion" -m "$tagannotation" $taghash
                    echo "git tag -a '$tagversion' -m '$tagannotation' $taghash"
                else
                    localbuffer="$localbuffer\n  git tag -a '$tagversion' -m '$tagannotation' $taghash"
                fi
            fi
            # Counting common version.php commit
            newcommits=$((newcommits+1))
        else
            # Failed bump the version - ensure we don't push up.
            output "  - ${R}Version file changes unsuccessful.${N}"
            _pushup=false
            return=1
        fi
    fi

    return $return;
}

# Argument 1: Release
get_release_tag_annotation() {
    local first=`expr match "$1" '\([0-9]\+\)'`
    local second=`expr match "$1" '[0-9]\+\.\([0-9]\+\)'`
    local third=`expr match "$1" '[0-9]\+\.[0-9]\+\.\([0-9]\+\)'`
    local beta=`expr match "$1" '[0-9\.]\+ \?beta'`
    local rc=`expr match "$1" '[0-9\.]\+ \?rc\([0-9]\)'`
    local forth=''
    if [[ beta -gt 0 ]] ; then
        forth='_BETA'
    fi
    if [[ rc -gt 0 ]] ; then
        forth="_RC$rc"
    fi
    if [[ second -lt 10 ]] ; then
        second="0${second}"
    fi
    echo "MOODLE_$first$second$third$forth"
}

# Argument 1: Release
get_release_tag_version() {
    local tag=''
    local release=`expr match "$1" '\([0-9\.]\+\)'`
    if [ -z `expr match "$release" '[0-9]\+\.[0-9]\+\(\.[0-9]\)'` ] ; then
        tag="v$release.0"
    else
        tag="v$release"
    fi
    local beta=`expr match "$1" '[0-9\.]\+ \?beta'`
    local rc=`expr match "$1" '[0-9\.]\+ \?rc\([0-9]\)'`
    local sub=''
    if [[ beta -gt 0 ]] ; then
        sub='-beta'
    fi
    if [[ rc -gt 0 ]] ; then
        sub="-rc$rc"

    fi
    echo "$tag$sub"
}

get_new_stable_branch() {
    local first=`expr match "$1" '\([0-9]\+\)'`
    local second=`expr match "$1" '[0-9]\+\.\([0-9]\+\)'`
    if [[ second -lt 10 ]] ; then
        second="0${second}"
    fi
    echo "MOODLE_${first}${second}_STABLE"
}

generate_upgrade_notes() {
    local type=$1

    cd ${mydir}/gitmirror
    if [ -f .grunt/upgradenotes.mjs ]; then
        # We are going to use a temporal file to capture stdout and stderr, in case something fails.
        tmpfile=$(mktemp) || \
            { output "    ${R}Failed to create temp file.${N}"; exit 1; }
        output "    - Installing NodeJS modules"
        # Capture output and error into a temporary file.
        nvm use > "${tmpfile}" 2>&1 || \
            output "      ${R}Error running nvm. Details:${N} $(<"${tmpfile}")"
        npm ci --no-progress > "${tmpfile}" 2>&1 || \
            output "      ${R}Error running npm ci. Details:${N} $(<"${tmpfile}")"
        output "    - Generating upgrade notes"
        if [ $type == "major" ] || [ $type == "minor" ]; then
            .grunt/upgradenotes.mjs release -d > "${tmpfile}" 2>&1 || \
                output "      ${R}Error running upgradenotes.mjs. Details:${N} $(<"${tmpfile}")"
        else
            .grunt/upgradenotes.mjs release > "${tmpfile}" 2>&1 || \
                output "      ${R}Error running upgradenotes.mjs. Details:${N} $(<"${tmpfile}")"
        fi
        rm -f "${tmpfile}"
    else
        output "    ${Y}Upgrade notes script not found.${N}"
    fi
}

show_help() {
    bold=`tput bold`
    normal=`tput sgr0`
    echo ""
    echo "${bold}Moodle release - prerelease.sh script${normal}"
    echo ""
    echo "This tool prepares the gitmirror moodle repository for the next release and then"
    echo "spreads it to the integration server."
    echo "Before running this tool you must have run the installation script, this needs "
    echo "to be done only once."
    echo ""
    echo "${bold}Usage:${normal}"
    echo "  ./prelease.sh [-b <branch>|--branch <branch>] [-h|--help] [-n|--not-forced]"
    echo "                [-p|--pushup] [-q|--quiet] [-t <type>|--type <type>]"
    echo ""
    echo "${bold}Arguments:${normal}"
    echo "The following options can be used to control how this script runs:"
    echo "  ${bold}-b${normal}, ${bold}--branch${normal}"
    echo "      Limits the operation to just the branch that has been given."
    echo "      By default the appropriate branches for the release type will all be"
    echo "      operated on."
    echo "      [${allbranches[@]}]"
    echo "  ${bold}-d${normal}, ${bold}--date${normal}"
    echo "      Enforces a build date for all the branches being processed. The use of"
    echo "      this option overrides the default behavior, that is the following:"
    echo "         1) \"next monday\" is used for major and minor releases."
    echo "         2) \"today\" is used for any other release type."
    echo "  ${bold}-n${normal}, ${bold}--not-forced${normal}"
    echo "      By default the version file on all branches will be bumped. If this option"
    echo "      has been specified then the version file will only be bumped if there are"
    echo "      new commits on the branch"
    echo "  ${bold}-p${normal}, ${bold}--pushup${normal}"
    echo "      By default this script prepares everything to be pushed by does not push."
    echo "      If this option is specified the staged commits and any tags will be pushed "
    echo "      up to the integration server."
    echo "  ${bold}-q${normal}, ${bold}--quiet${normal}"
    echo "      If set this script produces no progress output. It'll let you know when "
    echo "      its finished however."
    echo "  ${bold}-t${normal}, ${bold}--type${normal}"
    echo "      The type of release to prepare."
    echo "      [${_types[@]}]"
    echo "  ${bold}--no-create${normal}"
    echo "      If this tool finds that one of the expected branches does not exist then"
    echo "      by default it creates it. If this option is specified the tool will not"
    echo "      create the branch but will exit with an error."
    echo ""
    echo "  ${bold}-h${normal}, ${bold}--help${normal}"
    echo "      Prints this help and exits."
    echo ""
    echo "  ${bold}-r${normal}, ${bold}--reset${normal}"
    echo "      Use this (exclusive) option to discard any current change in the local git"
    echo "      clone (gitmirror dir), reseting it back to origin (integration.git)."
    echo "      Causes the script to exit."
    echo ""
    echo "  ${bold}-s${normal}, ${bold}--show${normal}"
    echo "      Use this (exclusive) option to display all the current changes applied"
    echo "      to the local git clone (gitmirror dir), comparing with integration.git."
    echo "      If this option is specified it will output what it wants and the script"
    echo "      Causes the script to exit."
    echo ""
    echo "If no arguments are provided to this script it prepares a weekly release on all "
    echo "expected branches."
    echo "For more information about the release process and how to go about it please "
    echo "have a look at:    https://github.com/moodlehq/mdlrelease"
    echo ""
    echo "${bold}Examples:${normal}"
    echo ""
    echo "  ${bold}./prerelease.sh${normal} runs a standard weekly release"
    echo "  ${bold}./prerelease.sh -b MOODLE_19_STABLE${normal} runs a weekly release for one branch"
    echo "  ${bold}./prerelease.sh -t minor${normal} runs a minor release"
    echo "  ${bold}./prerelease.sh -t major${normal} runs a major release for main only"
    echo "  ${bold}./prerelease.sh -t beta${normal} runs a beta release for main only"
    echo "  ${bold}./prerelease.sh -t rc 2${normal} runs a release for rc2 for main only"
    echo "  ${bold}./prerelease.sh -t on-demand${normal} runs a weekly on-demand (pre-release) for main only"
    echo "  ${bold}./prerelease.sh -t on-sync${normal} runs a weekly on-sync (post-release) for main only"
    exit 0
}

reset_repo() {
    for branch in ${allbranches[@]}; do
        git show-ref --verify --quiet refs/heads/$branch
        if [[ $? -ge 1 ]] ; then
            output "  - ${Y}Expected ${branch} not found.${N}";
            output "    It will be automatically created the next time you run a release";
            output "    unless you specify the --no-create option";
        else
            output "  - Reseting ${branch} to origin/${branch}."
            git checkout --quiet ${branch} && git reset --hard --quiet origin/${branch}
        fi
    done
    output "  - Discarding any modification in the worktree."
    git clean -dfxq
    exit 0
}

show_changes() {
    changedbranches=()
    counter=1
    for branch in ${allbranches[@]}; do
        git show-ref --verify --quiet refs/heads/$branch
        if [[ $? -ge 1 ]] ; then
            output "  - ${Y}Expected ${branch} not found.${N}";
            output "    It will be automatically created the next time you run a release";
            output "    unless you specify the --no-create option";
        else
            logs="$( git log origin/${branch}..${branch} --oneline )"
            if [[ -n "${logs}" ]] ; then
                count=$(echo "${logs}" | wc -l)
                output "  - Changes found in ${R}${branch}${N} (${count} commits). [${counter}]"
                changedbranches[${counter}]="${branch}"
                maxfound=${counter}
                let counter++
                IFS=$'\n'
                for line in ${logs} ; do
                    output "      -> ${line}"
                done
            else
                output "  - No changes in ${branch}"
            fi
        fi
    done
    output ""
    option="L" # Default to list status
    while [[ "${option}" != "Q" ]]  ; do
        case "${option}" in
            L)  # list status.
                if [ ${#changedbranches[@]} -eq 0 ]; then
                    output "There are no local changes. Friendly exiting. Bye!"
                    exit 0
                else
                    output "These branches have local changes"
                    for i in "${!changedbranches[@]}"; do
                        output "  [${i}] => ${changedbranches[$i]}"
                    done
                fi
                ;;

            A)  # show all branches details.
                if [ ${#changedbranches[@]} -eq 0 ]; then
                    output "There are no local changes. Friendly exiting. Bye!"
                    exit 0
                else
                    for i in "${!changedbranches[@]}"; do
                        output "You are going to view ${G}${changedbranches[$i]}${N} local changes. Press any key."
                        read -sn1
                        LESS=-+F-r git diff origin/${changedbranches[$i]}..${changedbranches[$i]}
                    done
                fi
                ;;

            [123456789]) # show 1 branch detail.
                if [[ ${option} -gt ${maxfound} ]]; then
                    output "Option out of range, please use a correct alternative [1..${maxfound}]."
                else
                    output "You are going to view ${G}${changedbranches[$option]}${N} local changes. Press any key."
                    read -sn1
                    LESS=-+F-r git diff origin/${changedbranches[$option]}..${changedbranches[$option]}
                fi
                ;;

            Q)  # bye!
                exit
                ;;

            *)
                output "Incorrect option, please try again!"
                ;;
        esac
        output ""
        output "${G}Pick an option ${N}([${R}L${N}]ist status, [[${R}1..${maxfound}${N}] => 1 branch details, [${R}A${N}]ll branches details, [${R}Q${N}]uit):"
        read -n1 option
        option=$(echo $option | tr '[:lower:]' '[:upper:]')
        output ""
    done
    exit 0
}

_showhelp=false
while test $# -gt 0;
do
    case "$1" in
        -b | --branch)
            shift # Get rid of the flag.
            _onlybranch="$1"
            shift # Get rid of the value.
            ;;
        -t | --type)
            shift # Get rid of the flag.
            if in_array "$1" "${_types[@]}"; then
                _type=$1
            else
                echo ""
                echo "${R}* Invalid type specified.${N}"
                _showhelp=true
            fi
            shift # Get rid of the value.
            if [ "$_type" = "rc" ] ; then
                _rc=$1
                shift # Get rid of the RC release value
            fi
            ;;
        -p | --pushup)
            # _pushup=true
            echo "${Y}* The pushup option has been disabled until we really trust this script.${N}"
            shift # Get rid of the flag.
            ;;
        -n | --not-forced)
            _forcebump=false
            shift # Get rid of the flag.
            ;;
        -q | --quiet)
            _verbose=false
            shift # Get rid of the flag.
            ;;
        -d | --date)
            shift # Get rid of the flag.
            _date="$1"
            shift # Get rid of the value.
            ;;
        -r | --reset)
            _reset=true
            shift # Get rid of the flag.
            ;;
        -s | --show)
            _show=true
            shift # Get rid of the flag.
            ;;
        -h | --help)
            _showhelp=true
            shift
            ;;
        --no-create)
            _nocreate=true
            shift
            ;;
         *)
            echo "${R}* Invalid option $1 given.${N}"
            _showhelp=true
            shift
    esac
done

if $_showhelp ; then
    show_help
fi

# Before anything else, let's check if, right now, it's a good time to run this script.

# Calculate a few timestamps (unix seconds since epoch).
curr=$(date -u +%s) # Now
publ=$(next_utc_time "${PUBLISHING_TIME}") # Publishing time UTC.

# Calculate some local and interval times.
publlocal=$(date -d @"${publ}" +'%H:%M:%S %Z')    # Publishing time in local time.
prevention=$((publ - PREVENT_MINUTES * 60))       # Begin of prevention time.

# If we are within the prevention time, let's prevent and exit.
if [ "${curr}" -gt "${prevention}" ]; then
    output "${Y}The packaging server is about to start processing git changes${N}"
    output "${Y}and it is not advisable to run this script while that happens.${N}"
    output ""
    output "${Y}Please wait until everything has been executed after ${PUBLISHING_TIME} UTC (${publlocal}).${N}"
    output "${R}Exiting.${N}"
    exit 1
fi

if [[ ! -d ${mydir}/gitmirror ]] ; then
    output "Directory ${mydir}/gitmirror not found. You may need to create it with install.sh"
    exit 1
fi
cd ${mydir}/gitmirror
pwd=`pwd`

# Perform a reset before anything else. It's an exlusive and final option.
if $_reset ; then
    output "${G}Reseting all local branches to origin and discarding worktree changes.${N}"
    reset_repo
fi

# Show all local changes before anything else. It's an exclusive and final option.
if $_show ; then
    output "${G}Show changes in all the local branches compared with integration.git.${N}"
    show_changes
fi

if [[ $_rc -gt 0 ]] ; then
    output "${G}Starting pre-release processing for release candidate $_rc release.${N}"
else
    output "${G}Starting pre-release processing for $_type release.${N}"
fi

# Check there are no changes in the repo that would cause us issues.
all_clean
output "  - Git repository clean"
output "  - Fetching remotes"

git fetch --all --prune --quiet
if [[ $? -ge 1 ]] ; then
    # Drat it failed to fetch updates - we've got to bail.
    echo "$?"
    output "Failed to fetch updates from the remote repositories"
    exit 1
fi

# Let's calculate the branches (or branch) we are going to operate on.
branches=()

# If the release is a major-related one, and no branch has been forced, and we are under parallel development
# let's pick the best default branch (first non-main one).
if in_array "$_type" "${_reltypes[@]}" && [ -z $_onlybranch ] && [ ${#devbranches[@]} -gt 1 ] ; then
    # There isn't any back-to-dev under parallel development. Next branch needs to
    # be created manually (if parallel continues), or is main that is already dev (if parallel ends).
    if [ "$_type" == "back-to-dev" ] ; then
        output "Invalid type \"back-to-dev\" specified under parallel development.";
        exit 1
    fi
    # The best branch when there are multiple is always the nearest to be released, usually the 1st non-main one.
    output "  - Major release related \"${_type}\" type detected under parallel development."
    output "  - Calculating the development branch to apply the changes to (note that"
    output "    this can be overridden using the --branch option to force a branch)"
    output "  - Candidates: ${devbranches[*]}"
    for branch in "${devbranches[@]}"; do
        if [ "${branch}" == "main" ]; then
            # If there are multiple, main isn't ever the next one.
            continue
        else
            # First one is the next one.
            branches=(${branch})
            break
        fi
    done
else
    # Establish the branches array by coping the relevant type array into it.
    case $_type in
        "weekly" )
            branches=(${weeklybranches[@]})
            ;;
        "minor" )
            branches=(${minorbranches[@]})
            ;;
        "major" )
            branches=(${devbranches[@]})
            ;;
        "beta" )
            branches=(${devbranches[@]})
            ;;
        "rc" )
            branches=(${devbranches[@]})
            ;;
        "on-demand" )
            branches=(${devbranches[@]})
            ;;
        "on-sync" )
            branches=(${devbranches[@]})
            ;;
        "back-to-dev" )
            branches=(${devbranches[@]})
            ;;
    esac
fi

if [ ${#branches[@]} = 0 ] ; then
    # Obviously they didn't provide a valid type, there are no branches.
    output "Invalid type specified. This should never happen."
    exit 1
fi

branchesstr=$(printf ", %s" "${branches[@]}")
if [[ $_onlybranch != '' ]] ; then
    if in_array "$_onlybranch" "${branches[@]}"; then
        # Only one branch requested and it's valid. Simplify the branches array.
        branches=($_onlybranch)
    else
        output "${R}The requested branch $_onlybranch is not a valid choice (${branchesstr:2}) for this release type${N}"
        exit 1
    fi
fi

output "  - Operating on $_type branches: ${branchesstr:2}"

# We're going to iterate over all branches, fast-forward them and then merge install strings.
for branch in ${branches[@]};
    do

    output "${G}Processing $branch${N}"

    # Ensure the branch exists. Useful when there have been major releases
    # since you last ran this tool.
    git show-ref --verify --quiet refs/heads/$branch
    if [[ $? -ge 1 ]] ; then
        # New branch.
        if $_nocreate ; then
            output "${R} Error: expected branch $branch does not exist.${N}"
            exit 1
        fi
        output "  - Expected branch $branch does not exist, creating it now."
        git checkout --quiet -b $branch refs/remotes/origin/$branch
    else
        # Existing branch - change to it.
        git checkout --quiet $branch
        if [[ $? -ge 1 ]] ; then
            output "${Y}Failed to checkout $branch, skipping.${N}"
            continue;
        fi

        # Reset it.
        git reset --quiet --hard origin/$branch
        if [[ $? -ge 1 ]] ; then
            output "${Y}Failed to reset $branch, skipping.${N}"
            continue;
        fi
    fi

    # We don't want any untracked files (vendor, node_modules, etc) before starting to process the branch.
    git clean -Xdf --quiet

    # Set default operations.
    fixpermissions=true
    fixsvg=true
    mergestrings=true
    upgradenotes=true

    # Determine if it's a development branch.
    isdevbranch=
    if in_array "$branch" "${DEVBRANCHES[@]}"; then
        isdevbranch=1
    fi

    # Check that we do actually want to process this branch.
    if [ -n "$isdevbranch" ]; then
        # Development branches are included in everything except a minor release.
        if [ "$_type" == "minor" ] ; then
            # It's a minor release so we don't do anything with development branches..
            output "${Y}Skipping $branch as it's a minor release.${N}"
            continue
        fi
        # Get the segment of the stable branch name to use for merges.
        stable=`expr "$branch" : 'MOODLE_'`
        mergestringsbranch="install_${branch:$stable}"
        # TODO: Remove these 2 lines once AMOS starts generating the install_main branch.
        mergestringsbranch="install_master"
    else
        # Must be a stable branch.
        # Stable branches are not included in major, beta, or rc releases.
        if [ "$_type" == "major" ] ; then
            # It's a major release so we don't do anything with the stable branches.
            output "${Y}Skipping $branch as it's a major release.${N}"
            continue
        fi
        if [ "$_type" == "beta" ] ; then
            # It's a beta release so we don't do anything with the stable branches.
            output "${Y}Skipping $branch as it's a beta release.${N}"
            continue
        fi
        if [ "$_type" == "rc" ] ; then
            # It's a rc release so we don't do anything with the stable branches.
            output "${Y}Skipping $branch as it's a rc release.${N}"
            continue
        fi
        if [ "$_type" == "on-demand" ] ; then
            # It's a on-demand release so we don't do anything with the stable branches.
            output "${Y}Skipping $branch as it's a on-demand release.${N}"
            continue
        fi
        if [ "$_type" == "on-sync" ] ; then
            # It's a on-sync release so we don't do anything with the stable branches.
            output "${Y}Skipping $branch as it's a on-sync release.${N}"
            continue
        fi
        # Get the segment of the stable branch name to use for merges.
        stable=`expr "$branch" : 'MOODLE_'`
        mergestringsbranch="install_${branch:$stable}"
        # TODO: Remove next 10 lines.
        version=${branch:$stable:2}
        if (( "$version" < 24 )) ; then
            # Version less than Moodle 24
            fixsvg=false
        fi
        if (( "$version" < 23 )) ; then
            # Version less than Moodle 23
            mergestrings=false
            fixpermissions=false
        fi
    fi

    # Now merge in install strings.
    if $mergestrings ; then
        output "  - Merging install strings..."
        git fetch --quiet https://git.in.moodle.com/amosbot/moodle-install.git $mergestringsbranch && git merge FETCH_HEAD --no-edit --quiet
    fi

    # Now fix SVG images if need be.
    if $fixsvg ; then
        output "  - Fixing SVG permissions..."
        php ${mydir}/fixsvgcompatability.php --ie9fix --path=$pwd

        if git_unstaged_changes ; then
            # Add modifications and deletions.
            git add -u
            if git_staged_changes ; then
                git commit --quiet -m "NOBUG: Fixed SVG browser compatibility"
            fi
            # Make sure everything is clean again.
            all_clean
            output "    ${Y}Fixes made as required.${N}"
        fi
    fi

    if $fixpermissions ; then
        output "  - Fixing file permissions..."
        php ${mydir}/fixpermissions.php $pwd
        if git_unstaged_changes ; then
            # Add modifications and deletions.
            git add -u
            if git_staged_changes ; then
                git commit --quiet -m "NOBUG: Fixed file access permissions"
            fi
            # Make sure everything is clean again.
            all_clean
            output "    ${Y}Permissions fixed as required.${N}"
        fi
    fi

    # Now generate the upgrade notes.
    if $upgradenotes ; then
        output "  - Generating upgrade notes..."
        generate_upgrade_notes "$_type"
        if git_unstaged_changes ; then
            # Add any upgrade files.
            git add -A
            if git_staged_changes ; then
                git commit --quiet -m "NOBUG: Add upgrade notes"
            fi
            # Make sure everything is clean again.
            all_clean
            output "    ${Y}Upgrade notes generated as required.${N}"
        fi
    fi

    # Determine if we need to push this branch up to the integration server.
    newcommits=`git rev-list HEAD...origin/$branch --ignore-submodules --count`

    if (( $newcommits > 0 )) || $_forcebump ; then
        # Bump the version file.
        output "  - Bumping version."
        if bump_version "$branch" "$_type" "$pwd" "$_rc" "$_date" "$isdevbranch"; then
            # Yay it worked!
            if [ "$isdevbranch" ] && [ "$_type" == "major" ] ; then
                output "  - Don't forget to read the notes."
            fi
        fi
    fi

    if (( $newcommits > 0 )) ; then
        # TODO: Delete these 7 lines (comments and if block) once we delete master.
        # Ensure that, always, master is the same as main.
        if [[ "${branch}" == "main" ]]; then
            git branch -f master main
            integrationpush="$integrationpush master"
            output "  - ${Y}master branch updated to match main branch.${N}"
        fi
        output "  + ${C}$branch done! $newcommits new commits to push.${N}"
        integrationpush="$integrationpush $branch"
    else
        output "  + ${C}$branch done! No new commits to push.${N}"
    fi
done;

if $_pushup ; then
    # We're going to push to integration... man I hope this worked OK.
    output "${G}Pushing modified branches to the integration server${N}..."
    git push origin $integrationpush
    output ""
    echo "Pre-release processing has been completed and all changes have been propagated to the integration repository"

else
    # We're not pushing up to integration so instead give the integrator the commands to do so.
    localbuffer="${C}\n  git push origin$integrationpush\n  $localbuffer${N}"
    output ""
    echo "${G}Pre-release processing has been completed.${N}"
    echo ""
    echo "Changes can be reviewed using the ${C}--show${N} option."
    if [ $_type = "major" ] ; then
        echo "  (you may want to update config.sh branches before using it, see notes below)"
    fi
    echo ""
    if [ $_type = "weekly" ] ; then
        echo "Changes have ${R}not${N} been propagated to the integration repository. If you wish to do this run the following:"
    else
        echo "Please propagate these changes to the integration repository with the following:"
    fi
    printf "$localbuffer\n";
    # If any tag has been added locally, add a comment about CI and pushing the tag.
    if [[ $localbuffer =~ 'git tag -a' ]]; then
        echo ""
        echo "Once CI jobs ${R}have ended successfully${N}, you can safely push the release tag(s) to the integration repository:"
        echo ""
        echo "${C}  git push origin --tags${N}"
    fi
fi
echo ""

if [ $_type == "major" ] || [ $_type == "minor" ]; then
    if [ $_type == "major" ] ; then
        echo "${Y}Notes${N}: "
        if [ ${#devbranches[@]} -gt 1 ]; then
            echo "  - This has been a major release ${R}under parallel development${N}. It implies that the"
            echo "    STABLE branch released already existed, hence no new branch has been created by this tool."
            echo "    - Important: If the parallel development period is going to continue with a new STABLE branch and main"
            echo "      then, in few weeks, once the on-sync period ends, you will have to:"
            echo "      - Create the new MOODLE_XYZ_STABLE branch manually (branching from the STABLE branch just released)."
            echo "      - Modify all the related places needing to know about that new branch (security, CI, tracker, this tool config.sh..."
            echo "        (basically this implies to review all the Moodle Release Process check-list and perform all the"
            echo "        actions detailed there for a new branch - but without releasing it, heh, it's a dev branch!)."
            echo "    - If the parallel development period has ended, no further actions are needed, development will"
            echo "      be back to normal, main-only"
        else
            echo "  - As this was a major release you will need to ${R}update config.sh${N} to include the new stable branch as an expected branch."
        fi
    fi
    echo "  - Follow the ${R}instructions and steps order${N} for major and minor releases @ https://docs.moodle.org/dev/Release_process#Packaging."
    # As of 20240209 iTeam agreed to keep the jobs enabled always, infrastructure is capable. Hence, commenting out next line.
    # echo "  - If the ${R}'Rebase security branch'${N} jobs have been disabled (as part of the security2integration task), now it's time to enable them back, so they catch up with current code and are ready for next week. The ${R}'MAINT - Toggle (enable, disable) jobs by name'${N} @ CI job can be used to do that."
    echo ""
elif [ $_type == "on-sync" ]; then
    echo "${Y}Notes${N}: "
    echo "  - Don't forget that ${R}the last week of on-sync${N} it's better to perform a ${R}normal main release (weekly)${N} in order to guarantee that versions have diverged. If this is such a week, please proceed accordingly."
    echo "  - IMPORTANT: If this is ${R}the last week of on-sync${N}, don't forget to run all the actions that are documented together in the Point 1 of the \"2 weeks after\" section of the Moodle Release Process (link: https://docs.moodle.org/dev/Release_process#2_weeks_after). It is ${R}highly recommended to execute ALL the actions in that Point 1${N} immediately after moving out from on-sync (before the next week begins).${N}"
    echo ""
elif [ $_type == "back-to-dev" ]; then
    echo "${Y}Notes${N}: "
    echo "  - This is a "back-to-dev" release. And it's the ${R}unique type of release${N}"
    echo "    ${R}effectively incrementing the "\$release" and "\$branch" in the main version.php file${N}."
    echo ""
    echo "    Triple ensure that both variables are correct for the next planned major. It may be that you"
    echo "    want to jump to the next X+1.00 version instead of continue the X series. For example,"
    echo "    after release 7.23... you may want to jump to 8.0 instead of the, calculated by default, 7.24."
    echo ""
    echo "    If that's the case, please ${R}amend the commit manually to make both "\$release"${N}"
    echo "    ${R}and "\$branch" to point to the correct next major planned release${N}."
    echo ""
fi
