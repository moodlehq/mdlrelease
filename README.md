Moodle release tool
===================

This project contains the scripts used to prepare Moodle code for Major, Minor and weekly releases.

* [About this tool](#about-this-tool)
* [Installation](#installation)
* [Update](#update)
* [Release types](#release-types)
    * [Weekly release](#weekly-release)
    * [Minor release](#minor-release)
    * [Beta release](#beta-release)
    * [RC release](#rc-release)
    * [Major release](#major-release)
* [Advanced release types](#advanced-release-types)
    * [On-demand release](#on-demand-release)
    * [On-sync release](#on-sync-release)
    * [Back-to-dev release](#back-to-dev-release)
* [After the release](#after-the-release)
* [Pre-release options](#pre-release-options)
* [Advanced pre-release options](#advanced-pre-release-options)
    * [Reset the local repository](#reset-the-local-repository)
    * [Show the state of the local repository](#show-the-state-of-the-local-repository)


About this tool
----------------------
This tool helps you prepare a Moodle release.
 It includes details on the required steps and scripts to facilliate those steps.
 In producing a release you simple need to work through the steps.

As this involves pushing Moodle source code to the public git repositories we maintain you must ensure you have had your
 ssh keys added to the following repositories by an administrator.

* git://git.moodle.ord/moodle.git
* git://github.com/moodle/moodle.git
* git@bitbucket.org:moodle/moodle.git

Installation
------------
Before running releases this project needs to be set up. This only need to be done the very first time you use this tool.
Set up is dead easy thanks to a the installation script:

    git clone git://github.com/moodlehq/mdlrelease.git
    cd mdlrelease
    ./install.sh protocol username

protocol: defaults to 'ssh', also accepts 'https'.
username: ignored for 'ssh' (keys are used there), optional for 'https' (git will ask for it if not set).

For more info about the protocols and their differences, read the "Setup required for new iTeam member" article.

The installation script prepares a Moodle repository and the necessary branches. As this involves a Moodle checkout the process can take some time to complete.
Please be patient.

### MacOS users
These scripts rely on some GNU utilities, and macOS ships the BSD flavor of command line tools instead of the GNU flavor. Homebrew can be use to install the GNU versions of tools, as well as MacPorts.

**Homebrew**

You can install most of the GNU flavored tools with:

    brew install autoconf bash binutils coreutils diffutils ed findutils flex gawk \
        gnu-indent gnu-sed gnu-tar gnu-which gpatch grep gzip less m4 make nano \
        screen watch wdiff wget

To use the GNU versions by default, append to your ~/.profile ({~/.zprofile if you use *zsh*):

    # Get list of gnubin directories
    export GNUBINS="$(find /usr/local/opt -type d -follow -name gnubin -print)";
    
    if type brew &>/dev/null; then
      HOMEBREW_PREFIX=$(brew --prefix)
      for d in ${HOMEBREW_PREFIX}/opt/*/libexec/gnubin; do export PATH=$d:$PATH; done
    fi

Source: https://gist.github.com/skyzyx/3438280b18e4f7c490db8a2a2ca0b9da

**MacPorts**

To install the port:
    
    sudo port install gnutls

Add them to your PATH:

    PATH="/opt/local/libexec/gnubin:$PATH"; export PATH

Release types
-----------------

There are several different types of release that can be made by this tool. In fact it covers all of the planned release types.

**Weekly release** - the most common release, made every week and containing all the newly integrated work. Includes master and all stables branches.

**Minor release** - every two to three months we make a minor release, this release type handles everything involved with that. Only stable branches are worked on here.

**Beta release** - Around a month or two before a major release we create a beta release of the master branch. This usually occurs when QA testing starts and only affects the master branch.

**RC release** - The release candidate release. Usually one or more release candidate releases are prepared for the master branch in the final lead up to the major release. Again master only.

**Major release** - twice a year we make a major release (and usually a minor release of stables at the same time). This affects only the master branch although it leads to the next stable branch being produced.

There are also a handful of advanced release types. These release types are usually very specific and you may - or may not be required to run them. As such they are unplanned and usually only required if the situation demands it.

### Weekly release

The following steps must be followed to perform a weekly release.

**0. Ensure that we have achieved true roll-ability**

Only possible when:

- CI status is green (only controlled problems allowed).
- Every ongoing issue under current integration has a valid outcome (integrated, reopened, delayed) applied.
- There aren't remaining issues under testing. That may imply that you have to pass some issues under automated testing on CiBoT's behalf.

**1. Run the pre-release script.**

    ./prerelease.sh

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.
This option is useful if you trust the script more than you should ;)

**2. Review the branches very carefully.**

Note that weekly rolls involve, exclusively, supported branches. Always. No distinction the weeks before releases or the on-demand/on-sync explained below.

Only exception is when, after agreement, it has been decided to force something to land into unsupported branches and configuration is altered to cause that (for example environment.xml changes case).

**3. Push changes to the integration repository**

Use the command provided by **prerelease.sh** to push these last changes to the integration repository.

**4. Confirm that all is green**

http://integration.moodle.org jobs chain will start once last version bump is pushed to integration. Wait until all tests finish to confirm that all branches are ready to be public.

**5. Push to public repository**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

**6. Complete remaining tasks**

Follow the [After the release](#after-the-release) steps where needed.

### Minor release

**Note:** Minor releases are usually produced on the weekend, a **normal weekly** is produced for stable supported branches on the weekdays before it.

The following steps must be followed to perform a minor release.
The minor release affects all stable branches, master however is skipped as you cannot produce a minor release on an unreleased branch.

**1. Run the pre-release script.**

    ./prerelease.sh --type minor

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push changes to the integration repository**

Use the command provided by **prerelease.sh** to push these last changes to the integration repository. **Don't push tags** at this stage.

**4. Confirm that all is green**

http://integration.moodle.org jobs chain will start once last version bump is pushed to integration. Wait until all tests finish to confirm that all branches are ready to be public. Once verified and passing, **push tags** following the commands provided by **prerelease.sh**.

**5. Push to public repository**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

**6. Complete remaining tasks**

Follow the [After the release](#after-the-release) steps where needed. Then, continue with the [Moodle release process](https://docs.moodle.org/dev/Release_process#Releasing) for major versions.

### Beta release

The following steps must be followed to perform a beta release.
The beta release puts the master branch into a beta state. This usually happens in conjunction with QA testing, however it can happen any time we feel the master branch is maturing + stabilising in the lead up to a major release. Stable branches are skipped of course.

**1. Run the pre-release script.**

    ./prerelease.sh --type beta

Note that we might want to release stable branches together with a master beta, if that is the case we use the following command instead:

    ./prerelease.sh --type weekly
    # Overwrite weekly master branch with a master beta.
    ./prerelease.sh --type beta

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push changes to the integration repository**

Use the command provided by **prerelease.sh** to push these last changes to the integration repository.

**4. Confirm that all is green**

http://integration.moodle.org jobs chain will start once last version bump is pushed to integration. Wait until all tests finish to confirm that all branches are ready to be public.

**5. Push to public repository**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

**6. Complete remaining tasks**

Follow the [After the release](#after-the-release) steps where needed.

### RC release

The following steps must be followed to perform a release candidate release.
There can be one or more release candidate releases made as the final build up to a major release. They signify that we believe master branch is now stable and that we don't expect to find any more significant issues before release. We have usually addressed all QA related issues and release blocking issues. Again master only.

**1. Run the pre-release script.**

    ./prerelease.sh --type rc 2

Where 2 is the release candidate version.

Note that we might want to release stable branches together with a master RC, if that is the case we use the following command instead:

    ./prerelease.sh --type weekly
    # Overwrite weekly master branch with a master RC.
    ./prerelease.sh --type rc 2

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push changes to the integration repository**

Use the command provided by **prerelease.sh** to push these last changes to the integration repository.

**4. Confirm that all is green**

http://integration.moodle.org jobs chain will start once last version bump is pushed to integration. Wait until all tests finish to confirm that all branches are ready to be public.

**5. Push to public repository**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

**6. Complete remaining tasks**

Follow the [After the release](#after-the-release) steps where needed.

### Major release

The following steps must be followed to perform a major release.
Hurrah - we are ready for a major release, a twice yearly occasion normally. This will take the master branch only, produce a major release, and then create the next stable branch.
After running this release type you will be required to edit these scripts and add a reference to the newly created stable release so that it is included in the processes here-after.

**1. Run the pre-release script.**

    ./prerelease.sh --type major

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Update the mdlrelease scripts**

Add support for the new branch in install.sh, prerelease.sh, and release.sh

**4. Prepare the current integration server**

Create a new repo, view and jobs (cloning from master) in the Jenkins servers, so the new branch becomes tested by 1st time.

**5. Double check everything.**

Verify integration.git looks 100% perfect before continuing

**6. Push to the public repositories**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

**6. Complete remaining tasks**

Follow the [After the release](#after-the-release) steps where needed. Then, continue with the [Moodle release process](https://docs.moodle.org/dev/Release_process#Releasing) for major versions.

Advanced release types
----------------------

The following advanced release types are also available.

### On demand release

Used to produce an on-demand release for the master branch. This type of release skips all stable branches.
The following steps must be followed to perform an on-demand release.

**1. Run the pre-release script.**

    ./prerelease.sh --type on-demand

Note that most of the time we also release stable branches together with a master on-demand, if that is the case we use the following command instead:

    ./prerelease.sh --type weekly
    # Overwrite weekly master branch with an on-demand master.
    ./prerelease.sh --type on-demand

Also, note that every on-demand release adds unconditionally the plus ('+') at the end of the version, so these changes are expected and normal: dev => dev+, beta => beta+, rc1 => rc1+ ...

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push changes to the integration repository**

Use the command provided by **prerelease.sh** to push these last changes to the integration repository.

**4. Confirm that all is green**

http://integration.moodle.org jobs chain will start once last version bump is pushed to integration. Wait until all tests finish to confirm that all branches are ready to be public.

**5. Push to public repository**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

**6. Complete remaining tasks**

Follow the [After the release](#after-the-release) steps where needed.

### On sync release

Used to produce an on-sync release for the master branch. This type of release skips all stable branches.
This release type should only be used when the Moodle's master branch must stay "in sync" with the latest stable branch after a major release. Note that the last week of the period, when on-sync ends, it's better to perform a normal master release (weekly) in order to guarantee that versions have diverged and avoid potential problems.
The following steps must be followed to perform an on-sync release.

**0. Ensure that we have achieved true roll-ability**

Only possible when:

- CI status is green (only controlled problems allowed).
- Every ongoing issue under current integration has a valid outcome (integrated, reopened, delayed) applied.
- There aren't remaining issues under testing. That may imply that you have to pass some issues under automated testing on CiBoT's behalf.

**1. Run the pre-release script.**

    ./prerelease.sh --type on-sync

Note that most of the time we also release stable branches together with a master on-sync, if that is the case we use the following command instead:

    ./prerelease.sh --type weekly
    # Overwrite weekly master branch with an on-sync master.
    ./prerelease.sh --type on-sync

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

Note that the **last week of on-sync**, it's better to perform a normal master release (weekly) in order to guarantee that versions have diverged. If this is such a week, please proceed accordingly.

**IMPORTANT:** If this is the **last week of on-sync**, don't forget to disable the [Continuous queues manager job](https://  ci.moodle.org/view/Tracker/job/TR%20-%20Manage%20queues%20on%20continuous/) right now to prevent it to continue processing issues once on-sync is finished.


**2. Review the branches very carefully.**

**3. Push changes to the integration repository**

Use the command provided by **prerelease.sh** to push these last changes to the integration repository.

**4. Confirm that all is green**

http://integration.moodle.org jobs chain will start once last version bump is pushed to integration. Wait until all tests finish to confirm that all branches are ready to be public.

**5. Push to public repository**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

**6. Complete remaining tasks**

Follow the [After the release](#after-the-release) steps where needed.

### Back to dev release

Used to produce a back-to-dev release after a major release.
This release type should only be used immediately after a major release has been successfully completed in order to set the master branch back to a development state.
The following steps must be followed to perform an on-sync release.

**1. Run the pre-release script.**

    ./prerelease.sh --type back-to-dev

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push to the public repositories**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

After the release
-----------------

You're not quite done yet, you must do the following after all releases.

**0. Verify external testing counters**

Note: this only applies to releases where **real issues have been fixed**, usually weeklies, on-demand, on-sync, not for "tagging" releases like betas, rcs, minors, majors (unless they come with any new issue fixed).

**Before closing the issues in the Tracker** and everything else, please visit the **[Sorted testing assignments](https://docs.google.com/spreadsheets/d/1vQQ1Y0vC8KGwPN2iHy3Guki5T-raBUnfrGT9ePLGqaE/edit#gid=0)** sheet and verify that the numbers in the *"External"* tab have been updated for the current cycle.

If not, and **the cycle does not exist** yet, fill both the *Week* and *Date (start of Week)* in a new row (bottom-up) and press the *"Update empty week row"* button. That will fetch all the information and fill the row properly.

If they were just **outdated but the row already existed**, you can simply clean the pink cells contents and press the button in order to get the information fetched.

**1. Update our records.**

Note: for **all release types**, no matter the counters are zero.

Annotate the number of closed, reopened and delayed issues in [the sheet](https://docs.google.com/a/moodle.com/spreadsheets/d/1EzYuIRYLEi3rKnzCVOV89gpFqhWUX8DyTQ6JCe9MPig/edit?usp=sharing). Note: You may need to check the integration dashboard to verify the total number of delayed issues, as some issues (those in progress still, for example) won't have been officially delayed yet (won't show in the count) but will need to be classified as such for the weekly statistics. Delaying is a process that each integrator handles for their own issues, however, any in progress issues are deemed delayed at this stage.

**2. Tidy up tracker.**

Note: for **all release types**, as far as there are issues to close.

Run the [Close tested issues](https://ci.moodle.org/view/Tracker/job/TR%20-%20Close%20tested%20issues/) job in the CI server. It will close all the tested issues under current integration (you can provide an alternative date or comment there). It will perform these changes:

   * Status: Closed
   * Change resolution: Fixed
   * Change integration date: Today's date
   * Change currently in integration -> None
   * Default thanks message.

Note: If there is any problem with the job, still it's possible to proceed using Tracker's bulk actions (transitioning to closed). If using this, you can temporarily disable the autowatch user preference to avoid autowatching all those issues.

Don't forget to review any [closed issue having "mdlqa" or "mdlqa_conversion" label](https://tracker.moodle.org/issues/?filter=14804). They may need resetting or moving associated MDLQA issues. There is a widget about this in the integration Dashboard too.

**3. Spam the tracker.**

Note: Only under normal integration periods. Aka, **not under continuous integration**.

Run the [Send rebase message](https://ci.moodle.org/view/Tracker/job/TR%20-%20Send%20rebase%20message/) job in the CI server. It will send the [standard rebase message](https://drive.google.com/open?id=1AjuyJKit4X4mk7aZL-28slydSPibt0yTDKCAl_egrxo#heading=h.xihdue23zgbu) to all issues awaiting for integration for the next week (you can provide an alternative comment there).

Note: If there is any problem with the job, still it's possible to proceed using Tracker's bulk actions (sending a comment). If using this, you can temporarily disable the autowatch user preference to avoid autowatching all those issues.

**4. Let the world know.**

Note: Only under normal integration periods. Aka, **not under continuous integration**. Unless there is something relevant enough and agreed to be shared.

Add one entry to the ["Integration, exposed"](https://moodle.org/mod/forum/view.php?f=1153) forum, commenting about numbers, major ones, special thanks... **always** checking the ["Integration exposed ideas"](https://docs.google.com/a/moodle.com/document/d/14hjHA_SrO2RRIUmJs9Fv23dV-O5FsHM5PT931Qe8tsQ/edit?usp=sharing) document for both general and [policy issues](https://tracker.moodle.org/browse/MDLSITE-6092) to be shared or proposed, together with other interesting topics worth commenting and thanks suggestions.

Pre-release options
-------------------

The following options can be used to customise how the selected release type is prepared.

* **-b | --branch**

    Limits the operation to just the branch that has been given. By default the appropriate branches for the release type will all be operated on.

* **-d | --date**

    Enforces a build date for all the branches being processed. The use of this option overrides the default behavior, that is the following:
    1. "next monday" is used for major and minor releases.
    2. "today" is used for any other release type.

* **-n | --not-forced**

    By default the version file on all branches will be bumped. If this option has been specified then the version file will only be bumped if there are new commits on the branch

* **-p | --pushup**

    By default this script prepares everything to be pushed by does not push.
    If this option is specified the staged commits and any tags will be pushed up to the integration server.

* **-q | --quiet**

    If set this script produces no progress output. It'll let you know when its finished however.

* **-t | --type**

    The type of release to prepare.

* **--no-create**

    If this tool finds that one of the expected branches does not exist then by default it creates it. If this option is specified the tool will not create the branch but will exit with an error.

Advanced pre-release options
----------------------------

The pre-release script has several arguments that can set to alter how the release is prepared, what steps are taken, and other functions to aid the integrator in the release process.

For detailed information on these options you can already run the following from your console:

    ./prerelease.sh --help

### Reset the local repository

This resets the expected branches in the local repository.

    ./prerelease.sh --reset

Please be aware that it doesn't actually fetch upstream changes, it simply resets the expected branches to the currently tracked state.
This is a particularly useful option if you've naughtily being hacking on the mdlrelease repository.

Note: this is an exclusive function, the prerelease script exits immediately after the reset operations have been completed.

### Show the state of the local repository

This displays the state of the local repository, making it clear if there are any changes that would get in the way of preparing a release.

    ./prerelease.sh --show

It also gives you the option of viewing the changes that have been made on a branch that is not in-sync with its tracked upstream branch.

Note: this is an exclusive function, the prerelease script exits immediately after the show operations have been completed.
