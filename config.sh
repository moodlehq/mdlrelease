#!/bin/bash
# This script contains the configuration for the mdlrelease scripts.
# It only needs to be updated after major releases at the moment as
# it contains only references to the stable and security branches.

# Current dev branches (always keep main the first).
DEVBRANCHES=('main')

# Current stable branches. (Later versions first)
STABLEBRANCHES=('MOODLE_403_STABLE' 'MOODLE_402_STABLE')

# Current security branches. (Later versions first)
SECURITYBRANCHES=('MOODLE_401_STABLE')
