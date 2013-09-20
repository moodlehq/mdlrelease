Moodle release tool
===================

This project contains the scripts used to prepare Moodle code for Major, Minor and weekly releases.

* [About this tool](#about-this-tool)
* [Installation](#installation)
* [Weekly release](#weekly-release)
* [Minor release](#minor-release)
* [Beta release](#beta-release)
* [RC release](#rc-release)
* [Major release](#major-release)
* [After the release](#after-the-release)

About this tool
----------------------
This tool helps you prepare a Moodle release.
 It includes details on the required steps and scripts to facilliate those steps.
 In producing a release you simple need to work through the steps.

As this involves pushing Moodle source code to the public git repositories we maintain you must ensure you have had your
 ssh keys added to the following repositories by an administrator.

* git://git.moodle.ord/moodle.git
* git://github.com/moodle/moodle.git
* git://gitorious.org/moodle/moodle.git

Installation
------------
Before running releases this project needs to be set up. This only need to be done the very first time you use this tool.
Set up is dead easy thanks to a the installation script:

    git clone git://github.com/moodlehq/mdlrelease.git
    cd mdlrelease
    ./install.sh yourmoodleusername

The installation script prepares a Moodle repository and the necessary branches. As this involves a Moodle checkout the process can take some time to complete.
Please be patient.

Weekly release
--------------
The following steps must be followed to perform a weekly release.

**1. Run the pre-release script.**

    ./prerelease.sh

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.
This option is useful if you trust the script more than you should ;)

**2. Review the branches very carefully.**

**3. Push to the public repositories**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

Minor release
--------------
The following steps must be followed to perform a weekly release.

**1. Run the pre-release script.**

    ./prerelease.sh --type minor

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push to the public repositories**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

Beta release
------------

The following steps must be followed to perform a weekly release.

**1. Run the pre-release script.**

    ./prerelease.sh --type beta

By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push to the public repositories**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

RC release
----------

The following steps must be followed to perform a weekly release.

**1. Run the pre-release script.**

    ./prerelease.sh --type rc 2

Where 2 is the release candidate version.
By default this script prepares the branches and gives you the commands to push. It doesn't actually push up to the integration server.
there is an optional argument *-p* which if specified pushes the updated branches to the integration repository.

**2. Review the branches very carefully.**

**3. Push to the public repositories**

Spread changes in integration to moodle.git and mirrors using ./release.sh (you may need, on releases, to comment some branches if not releasing all them together).

Major release
-------------
The following steps must be followed to perform a weekly release.

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

After the release
-----------------
You're not quite done yet, you must do the following after all releases.

**1. Tidy up tracker.**

Close all issues in Tracker (reseting linked MDLQA ones if existing)

**2. Update our records.**

Annotate the number of closed issues & the number of reopened ones (Tracker - CI - Count reopened issues) in the sheet.

**3. Let the world know.**

Add one entry @ http://moodle.org/mod/forum/view.php?f=1153 commenting about numbers, major ones, special thanks...