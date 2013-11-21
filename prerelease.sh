#!/bin/bash
# This script performs required pre-release processing.

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
# This script base dir
mydir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
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
_types=("weekly" "minor" "major" "beta" "rc" "on-demand" "on-sync" "back-to-dev");
_nocreate=false
localbuffer=""

# Try to observe the "master first, then stables from older to newer" rule.
weeklybranches=("master" "MOODLE_23_STABLE" "MOODLE_24_STABLE" "MOODLE_25_STABLE" "MOODLE_26_STABLE");
minorbranches=("MOODLE_23_STABLE" "MOODLE_24_STABLE" "MOODLE_25_STABLE" "MOODLE_26_STABLE");
majorbranches=("master");
betabranches=("master");
rcbranches=("master");

# Prepare an all branches array.
OLDIFS="$IFS"
IFS=$'\n'
allbranches=("${weeklybranches[@]}" "${minorbranches[@]}" "${majorbranches[@]}" "${betabranches[@]}" "${rcbranches[@]}")
allbranches=(`for b in "${allbranches[@]}" "${allbranches[@]}" ; do echo "$b" ; done | sort -du`)
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
bump_version() {
    local release=`php ${mydir}/bumpversions.php -b "$1" -t "$2" -p "$3" -r "$4" -d "$5"`
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
        # It's a on-demand relese... easy too!
        ondemand=true
    elif [ "$2" == "on-sync" ] ; then
        # It's a on-sync relese... easy too!
        onsync=true
    elif [ "$2" ==  "back-to-dev" ] ; then
        # Just returning master to dev after major
        backtodev=true
    elif [ "$1" == "master" ] && [ "$2" == "minor" ] ; then
        # It's the master branch and a minor release - master just gets a weekly.
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
                if $_pushup ; then
                    #git tag -a "$tagversion" -m "$tagannotation" $taghash
                    echo "git tag -a '$tagversion' -m '$tagannotation' $taghash"
                else
                    localbuffer="$localbuffer\ngit tag -a '$tagversion' -m '$tagannotation' $taghash"
                fi

                if [ "$1" == "master" ] && [ "$2" == "major" ] ; then
                    # Exciting
                    local newbranch=`get_new_stable_branch "$release"` # MOODLE_26_STABLE
                    output = "  - Creating new stable branch $newbranch"
                    git branch -f "$newbranch" master # create from (or reset to) master
                    integrationpush="$integrationpush $newbranch"
                fi

            fi
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
    echo "MOODLE_${first}${second}_STABLE"
}
output() {
    if $_verbose ; then
        echo "$1"
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
    echo "  ${bold}./prerelease.sh -t major${normal} runs a major release for master only"
    echo "  ${bold}./prerelease.sh -t beta${normal} runs a beta release for master only"
    echo "  ${bold}./prerelease.sh -t rc 2${normal} runs a release for rc2 for master only"
    echo "  ${bold}./prerelease.sh -t on-demand 2${normal} runs a weekly on-demand (pre-release) for master only"
    echo "  ${bold}./prerelease.sh -t on-sync 2${normal} runs a weekly on-sync (post-release) for master only"
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
                        LESS=-+F git diff origin/${changedbranches[$i]}..${changedbranches[$i]}
                    done
                fi
                ;;

            [123456789]) # show 1 branch detail.
                if [[ ${option} -gt ${maxfound} ]]; then
                    output "Option out of range, please use a correct alternative [1..${maxfound}]."
                else
                    output "You are going to view ${G}${changedbranches[$option]}${N} local changes. Press any key."
                    read -sn1
                    LESS=-+F git diff origin/${changedbranches[$option]}..${changedbranches[$option]}
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

# Establish the branches array by coping the relevant type array into it.
branches=()
case $_type in
    "weekly" )
        branches=(${weeklybranches[@]})
        ;;
    "minor" )
        branches=(${minorbranches[@]})
        ;;
    "major" )
        branches=(${majorbranches[@]})
        ;;
    "beta" )
        branches=(${betabranches[@]})
        ;;
    "rc" )
        branches=(${rcbranches[@]})
        ;;
    "on-demand" )
        branches=(${rcbranches[@]})
        ;;
    "on-sync" )
        branches=(${rcbranches[@]})
        ;;
    "back-to-dev" )
        branches=(${rcbranches[@]})
        ;;
esac

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

    # Set default operations.
    fixpermissions=true
    fixsvg=true
    mergestrings=true

    # Check that we do actually want to process this branch.
    if [ "$branch" == "master" ] ; then
        # master branch is included in everything except a minor release.
        if [ "$_type" == "minor" ] ; then
            # It's a minor release so we don't do anything with master.
            output "${Y}Skipping master as it's a minor release.${N}"
            continue
        fi
        mergestringsbranch="install_$branch"
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
        git fetch --quiet git://git.moodle.org/moodle-install.git $mergestringsbranch && git merge FETCH_HEAD --no-edit --quiet
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

    # Determine if we need to push this branch up to the integration server.
    newcommits=`git rev-list HEAD...origin/$branch --ignore-submodules --count`

    if (( $newcommits > 0 )) || $_forcebump ; then
        # Bump the version file.
        output "  - Bumping version."
        if bump_version "$branch" "$_type" "$pwd" "$_rc" "$_date"; then
            # Yay it worked!
            if [ "$branch" == "master" ] && [ "$_type" == "major" ] ; then
                output "  - Don't forget to read the notes."
            fi
        fi
    fi

    if (( $newcommits > 0 )) ; then
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
    localbuffer="\ngit push origin$integrationpush\n$localbuffer"
    output ""
    echo "Pre-release processing has been completed."
    echo "Changes can be reviewed using the --show option."
    if [ $_type = "weekly" ] ; then
        echo "Changes have ${R}not${N} been propagated to the integration repository. If you wish to do this run the following:"
    else
        echo "Please propogate these changes to the integration repository with the following:"
    fi
    printf "$localbuffer\n";
    # If any tag has been added locally, add a comment about CI and pushing the tag.
    if [[ $localbuffer =~ 'git tag -a' ]]; then
        echo ""
        echo "Once CI jobs have ended successfully, you can safely push the release tag(s) to the integration repository:"
        echo ""
        echo "git push origin --tags"
    fi
fi
echo ""

if [ $_type == "major" ] || [ $_type == "minor" ]; then
    if [ $_type == "major" ] ; then
        echo "${Y}Notes${N}: "
        echo "       As this was a major release you will need to update prerelease.sh to include the new stable branch as an expected branch."
        echo "       As this was a major release you will need to update release.sh to include the new stable branch when releasing"
    fi
    echo "       Follow the instructions for major and minor releases @ http://docs.moodle.org/dev/Release_process_(Combined)#Packaging."
    echo ""
fi
