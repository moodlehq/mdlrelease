#!/bin/bash

# master
cd gitmirror
git checkout master
git merge refs/remotes/origin/master
cd ..
diff -ru -I '\$\(Id\|Revision\|RCSfile\|Author\|Date\)[:$]' -x CVS -x .git gitmirror cvsmoodle

# 2.0 branch
cd gitmirror
git checkout MOODLE_20_STABLE
git merge refs/remotes/origin/MOODLE_20_STABLE
cd ..
diff -ru -I '\$\(Id\|Revision\|RCSfile\|Author\|Date\)[:$]' -x CVS -x .git gitmirror cvsmoodle20

# 1.9 branch
cd gitmirror
git checkout MOODLE_19_STABLE
git merge refs/remotes/origin/MOODLE_19_STABLE
cd ..
diff -ru -I '\$\(Id\|Revision\|RCSfile\|Author\|Date\)[:$]' -x CVS -x .git gitmirror cvsmoodle19

# and back to master
cd gitmirror
git checkout master
cd ..
