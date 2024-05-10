#!/bin/bash
# This script contains the configuration for the mdlrelease scripts.
# It only needs to be updated after major releases at the moment as
# it contains only references to the stable and security branches.

# Current dev branches (always keep main the first).
DEVBRANCHES=('main')

# Current stable branches. (Later versions first)
STABLEBRANCHES=('MOODLE_404_STABLE' 'MOODLE_403_STABLE')

# Current security branches. (Later versions first)
SECURITYBRANCHES=('MOODLE_402_STABLE' 'MOODLE_401_STABLE')

# UTC time when the publishing will be done. Keep this in sync with the downloads publishing time.
PUBLISHING_TIME='00:50:00'

# Time in minutes (before PUBLISHING_TIME) that we consider not advisable to start with pre-release tasks,
# because any delay with them may clash with the downloads publishing time, leading to some packages
# not being properly published. See MDLSITE-7681 for more details.
PREVENT_MINUTES='60'
