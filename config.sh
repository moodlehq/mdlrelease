#!/bin/bash
# This script contains the configuration for the mdlrelease scripts.
# It only needs to be updated after major releases at the moment as
# it contains only references to the stable and security branches.

# Current dev branches (always keep master the first).
DEVBRANCHES=('master')

# Current stable branches. (Later versions first)
STABLEBRANCHES=('MOODLE_402_STABLE' 'MOODLE_401_STABLE' 'MOODLE_400_STABLE')

# Current security branches. (Later versions first)
SECURITYBRANCHES=('MOODLE_311_STABLE' 'MOODLE_39_STABLE')
