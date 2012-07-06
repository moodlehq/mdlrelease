#!/bin/bash 

# This scripts propagates changes from official integration Moodle git repository
# to main repostiory and mirrors
#
# Please note you need to have public ssh keys in all remotes except the origin!

cd gitmirror
git fetch origin

git push --tags moodle refs/remotes/origin/master:refs/heads/master
git push --tags moodle refs/remotes/origin/MOODLE_23_STABLE:refs/heads/MOODLE_23_STABLE
git push --tags moodle refs/remotes/origin/MOODLE_22_STABLE:refs/heads/MOODLE_22_STABLE
git push --tags moodle refs/remotes/origin/MOODLE_21_STABLE:refs/heads/MOODLE_21_STABLE
# Discontinued 20120706 - git push --tags moodle refs/remotes/origin/MOODLE_20_STABLE:refs/heads/MOODLE_20_STABLE
git push --tags moodle refs/remotes/origin/MOODLE_19_STABLE:refs/heads/MOODLE_19_STABLE
# Discontinued - git push --tags moodle refs/remotes/origin/MOODLE_18_STABLE:refs/heads/MOODLE_18_STABLE
# Discontinued - git push --tags moodle refs/remotes/origin/MOODLE_17_STABLE:refs/heads/MOODLE_17_STABLE
# Discontinued - git push --tags moodle refs/remotes/origin/MOODLE_16_STABLE:refs/heads/MOODLE_16_STABLE
# Discontinued - git push --tags moodle refs/remotes/origin/MOODLE_15_STABLE:refs/heads/MOODLE_15_STABLE
# Discontinued - git push --tags moodle refs/remotes/origin/MOODLE_14_STABLE:refs/heads/MOODLE_14_STABLE
# Discontinued - git push --tags moodle refs/remotes/origin/MOODLE_13_STABLE:refs/heads/MOODLE_13_STABLE

git push --tags github refs/remotes/origin/master:refs/heads/master
git push --tags github refs/remotes/origin/MOODLE_23_STABLE:refs/heads/MOODLE_23_STABLE
git push --tags github refs/remotes/origin/MOODLE_22_STABLE:refs/heads/MOODLE_22_STABLE
git push --tags github refs/remotes/origin/MOODLE_21_STABLE:refs/heads/MOODLE_21_STABLE
# Discontinued 20120706 - git push --tags github refs/remotes/origin/MOODLE_20_STABLE:refs/heads/MOODLE_20_STABLE
git push --tags github refs/remotes/origin/MOODLE_19_STABLE:refs/heads/MOODLE_19_STABLE
# Discontinued - git push --tags github refs/remotes/origin/MOODLE_18_STABLE:refs/heads/MOODLE_18_STABLE
# Discontinued - git push --tags github refs/remotes/origin/MOODLE_17_STABLE:refs/heads/MOODLE_17_STABLE
# Discontinued - git push --tags github refs/remotes/origin/MOODLE_16_STABLE:refs/heads/MOODLE_16_STABLE
# Discontinued - git push --tags github refs/remotes/origin/MOODLE_15_STABLE:refs/heads/MOODLE_15_STABLE
# Discontinued - git push --tags github refs/remotes/origin/MOODLE_14_STABLE:refs/heads/MOODLE_14_STABLE
# Discontinued - git push --tags github refs/remotes/origin/MOODLE_13_STABLE:refs/heads/MOODLE_13_STABLE

git push --tags gitorious refs/remotes/origin/master:refs/heads/master
git push --tags gitorious refs/remotes/origin/MOODLE_23_STABLE:refs/heads/MOODLE_23_STABLE
git push --tags gitorious refs/remotes/origin/MOODLE_22_STABLE:refs/heads/MOODLE_22_STABLE
git push --tags gitorious refs/remotes/origin/MOODLE_21_STABLE:refs/heads/MOODLE_21_STABLE
# Discontinued 20120706 - git push --tags gitorious refs/remotes/origin/MOODLE_20_STABLE:refs/heads/MOODLE_20_STABLE
git push --tags gitorious refs/remotes/origin/MOODLE_19_STABLE:refs/heads/MOODLE_19_STABLE
# Discontinued - git push --tags gitorious refs/remotes/origin/MOODLE_18_STABLE:refs/heads/MOODLE_18_STABLE
# Discontinued - git push --tags gitorious refs/remotes/origin/MOODLE_17_STABLE:refs/heads/MOODLE_17_STABLE
# Discontinued - git push --tags gitorious refs/remotes/origin/MOODLE_16_STABLE:refs/heads/MOODLE_16_STABLE
# Discontinued - git push --tags gitorious refs/remotes/origin/MOODLE_15_STABLE:refs/heads/MOODLE_15_STABLE
# Discontinued - git push --tags gitorious refs/remotes/origin/MOODLE_14_STABLE:refs/heads/MOODLE_14_STABLE
# Discontinued - git push --tags gitorious refs/remotes/origin/MOODLE_13_STABLE:refs/heads/MOODLE_13_STABLE
