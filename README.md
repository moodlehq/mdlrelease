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

**1. Run the pre-release script.**

    ./prerelease.sh

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.
This option is useful if you trust the script more than you should ;)

**2. Review the branches very carefully.**

**3. Push changes to the integration repository**

Use the command provided by **prerelease.sh** to push these last changes to the integration repository.

**4. Confirm that all is green**

http://integration.moodle.org jobs chain will start once last version bump is pushed to integration. Wait until all tests finish to confirm that all branches are ready to be public.

**5. Push to public repository**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

### Minor release

The following steps must be followed to perform a minor release.
The minor release affects all stable branches, master however is skipped as you cannot produce a minor release on an unreleased branch.

**1. Run the pre-release script.**

    ./prerelease.sh --type minor

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push changes to the integration repository**

Use the command provided by **prerelease.sh** to push these last changes to the integration repository.

**4. Confirm that all is green**

http://integration.moodle.org jobs chain will start once last version bump is pushed to integration. Wait until all tests finish to confirm that all branches are ready to be public.

**5. Push to public repository**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

### Beta release

The following steps must be followed to perform a beta release.
The beta release puts the master branch into a beta state. This usually happens in conjunction with QA testing, however it can happen any time we feel the master branch is maturing + stabilising in the lead up to a major release. Stable branches are skipped of course.

**1. Run the pre-release script.**

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

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push changes to the integration repository**

Use the command provided by **prerelease.sh** to push these last changes to the integration repository.

**4. Confirm that all is green**

http://integration.moodle.org jobs chain will start once last version bump is pushed to integration. Wait until all tests finish to confirm that all branches are ready to be public.

**5. Push to public repository**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

### On sync release

Used to produce an on-sync release for the master branch. This type of release skips all stable branches.
This release type should only be used when the Moodle's master branch must stay "in sync" with the latest stable branch after a major release.
The following steps must be followed to perform an on-sync release.

**1. Run the pre-release script.**

    ./prerelease.sh --type on-sync

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push changes to the integration repository**

Use the command provided by **prerelease.sh** to push these last changes to the integration repository.

**4. Confirm that all is green**

http://integration.moodle.org jobs chain will start once last version bump is pushed to integration. Wait until all tests finish to confirm that all branches are ready to be public.

**5. Push to public repository**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

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

**1. Tidy up tracker.**

Close all issues in Tracker (reseting linked MDLQA ones if existing or moving them FROM MDLQA-1 to MDLQA-5249 if behat-covered). This can be done selecting all tested issues from the integration dash board -> Tools button -> Bulk action to all issues -> Transition -> Mark as committed, and changing:

   * Change resolution: Fixed
   * Change integration date: Today's date
   * Change currently in integration -> None
   * Add a nice comment

Hint: You can go to your JIRA user profile, edit your user preferences, set autowatch to 'Disabled' before performing the bulk action, and set it back later to the previous value, otherwise you will be autowatching all these issues.

**2. Spam the tracker.**

For a better next weekly release cycle, send the [roll and rebase](https://drive.google.com/open?id=1AjuyJKit4X4mk7aZL-28slydSPibt0yTDKCAl_egrxo#heading=h.xihdue23zgbu) warning to all the [issues awaiting integration](https://tracker.moodle.org/issues/?jql=project%20%3D%20MDL%20AND%20status%20%3D%20%22Waiting%20for%20integration%20review%22%20and%20labels%20not%20in%20(%27security_held%27)).

**3. Update our records.**

Annotate the number of closed, reopened and delayed issues in [the sheet](https://docs.google.com/a/moodle.com/spreadsheets/d/1EzYuIRYLEi3rKnzCVOV89gpFqhWUX8DyTQ6JCe9MPig/edit?usp=sharing).

**4. Let the world know.**

Add one entry to the ["Integration, exposed"](https://moodle.org/mod/forum/view.php?f=1153) forum, commenting about numbers, major ones, special thanks... Look to the ["Integration exposed ideas"](https://docs.google.com/a/moodle.com/document/d/14hjHA_SrO2RRIUmJs9Fv23dV-O5FsHM5PT931Qe8tsQ/edit?usp=sharing) document for issues and thanks suggestions.

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
