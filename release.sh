#!/bin/bash

# This scripts propagates changes from official integration Moodle git repository
# to main repostiory and mirrors
#
# Please note you need to have public ssh keys in all remotes except the origin!
#
# This script base dir

# Include lib.sh to get access to shared stuff..
if [ -f "$(dirname "${0}")"/lib.sh ]; then
    source "$(dirname "${0}")"/lib.sh
else
    echo "Unable to include lib.sh"
    exit 1
fi

# Prepare and array of all branches.
OLDIFS="$IFS"
IFS=$'\n'
# TODO: Remove master from the list once we delete it.
allbranches=($(for b in master "${DEVBRANCHES[@]}" "${STABLEBRANCHES[@]}" "${SECURITYBRANCHES[@]}" ; do echo "$b" ; done | sort -du))
IFS="$OLDIFS"

_verbose=true # Make lots of noise.
_showhelp=false
_confirmed=false
_dryrun=false
_tags=true

show_help() {
    echo ""
    echo "${bold}Moodle release - release.sh script${normal}"
    echo ""
    echo "This tool spreads the already prepared release from integration.git"
    echo "to moodle.git and then onto the other public repositories we maintain."
    echo ""
    echo "${Y}Warning:${normal} Only run this script after you have prepared the release!"
    echo ""
    echo "${bold}Usage:${normal}"
    echo "  ./release.sh [-c|--confirm] [-n|--dry-run] [-q|--quiet] [-h|--help]"
    echo "               [--no-tags]"
    echo ""
    echo "${bold}Arguments:${normal}"
    echo "  ${bold}-c${normal}, ${bold}--confirm${normal}"
    echo "      This script requires that you confirm the action you are about to take."
    echo "      By giving this argument you acknowledge you understand what is going to"
    echo "      be done and are happy for this script to just get on with it, without"
    echo "      prompting you to check."
    echo "  ${bold}-n${normal}, ${bold}--dry-run${normal}"
    echo "      Do everything except actually send the updates."
    echo "  ${bold}-q${normal}, ${bold}--quiet${normal}"
    echo "      If set this script produces no progress output. It'll let you know when "
    echo "      its finished however."
    echo "      You must confirm [-c|--confirm] if using this option."
    echo "  ${bold}-h${normal}, ${bold}--help${normal}"
    echo "      Prints this help and exits."
    echo "  ${bold}--no-tags${normal}"
    echo "      By default tags are pushed as well, this prevents that from happening."
    echo "      I hope you know what you're doing!"
    echo "  ${bold}-s${normal}, ${bold}--skip-version-check${normal}"
    echo "      By default the releaser detects if version.php has been updated. This "
    echo "      prevents that check and tries to pushes branches regardless."
    echo ""
    echo "May the --force be with you"
    exit 0;
}

while test $# -gt 0;
do
    case "$1" in
        -c | --confirm)
            _confirmed=true
            shift
            ;;
        -h | --help)
            _showhelp=true
            shift
            ;;
        -n | --dry-run)
            _dryrun=true
            shift
            ;;
        -q | --quiet)
            _verbose=false
            shift # Get rid of the flag.
            ;;
        --no-tags)
            _tags=false
            shift # Get rid of the flag.
            ;;
        -s | --skip-version-check)
            _skip_version_check=true
            shift # Get rid of the flag.
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

mydir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
if [[ ! -d ${mydir}/gitmirror ]] ; then
    output "Directory ${mydir}/gitmirror not found. You may need to create it with install.sh"
    exit 1
fi

output "${G}Moodle release propogator${N}"

cd ${mydir}/gitmirror
git fetch --quiet origin

# Prepare an array of all of the branches to push as refs.
pushbranches=()
skippedbranches=()

output
output "${normal}You are about to push:"
for b in "${allbranches[@]}" ; do
    # Search for a 'real' release by ensuring the top commit on version.php changes $release and was recent.
    releasebumped=$(git show --since='8 hours ago' -n1 origin/${b} version.php | grep "+\$release\s*=\s*")
    if [[ -n ${releasebumped} || $_skip_version_check ]]; then
        output "${G}$b: ${normal}$(git log -n1 --pretty=format:"%s (%an %ar)" origin/$b)"
        # Check if between the last commit in the branch and now, it has been PUBLISHING_TIME (UTC)
        # If that happens, then we cannot publish the branch and we'll need to rewrite the
        # integration.git history and pre-release again. See MDLSITE-7681 for more details.
        # We very-rarely should face this, as far as the pre-release script already detects
        # 99% of cases, but this is a last-resort check.
        comm=$(git log -n1 --pretty=format:"%ct" origin/"${b}") # Last commit time in seconds.
        curr=$(date -u +%s)                            # Now in seconds.
        publ=$(date -u -d "${PUBLISHING_TIME}" +%s)    # Publishing time in seconds.
        publlocal=$(date -d @"${publ}" +'%H:%M:%S %Z') # Publishing time in local time.

        if [ "${comm}" -lt "${publ}" ] && [ "${curr}" -gt "${publ}" ]; then
            output "  ${Y}Between the last commit and now, the packaging server${N}"
            output "  ${Y}has already processed the branch at ${PUBLISHING_TIME} (${publlocal}${N})"
            output "  ${Y}and pushing it now would lead to unpublished releases.${N}"
            output "  ${Y}Please rewrite history in integration.git and pre-release again.${N}"
            output "  ${R}Skipping branch.${N}"
            continue
        fi

        # Arrived here, all ok, this branch will be pushed.
        pushbranches+=("refs/remotes/origin/${b}:refs/heads/${b}")
    else
        skippedbranches+="$b "
    fi
done
output

if [ -z "$pushbranches" ]; then
    echo "${R}Error${normal}:  No branch changes detected. Exiting"
    exit 1
fi

if [ -n "$skippedbranches" ]; then
    output "${R}Ignoring: $skippedbranches (no version bump detected) $normal"
    output
fi

if ! $_confirmed ; then
    if ! $_verbose ; then
        echo "${R}Error${N}: you must auto-confirm [-c|--confirm] when running silent [-q|--quiet]"
        exit 1
    fi
    output "${bold}Confirm:${normal} please confirm you intention to update the public repositories with the"
    output "         prepared release: [y|n] " true
    read -n 1 confirminput
    if [ "$confirminput" != "y" ] && [ "$confirminput" != "Y" ] ; then
        output " ... release script ${bold}cancelled${normal}. Have a nice day!"
        exit 0
    else
        output " ... proceeding"
    fi
fi

output ""
output "${bold}Propogating${normal} ... " true

pushargs=""
if $_tags ; then
    pushargs="${pushargs} --tags"
fi
if $_dryrun ; then
    pushargs="${pushargs} --dry-run"
fi

# Update public repositories
#  * moodle         - git://git.moodle.org/moodle.git
#  * github         - git@github.com:moodle/moodle.git
git push ${pushargs} public ${pushbranches[@]}

output "${G}Done!${N}"
exit 0
