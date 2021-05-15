#!/bin/bash
# This script contains the configuration for the mdlrelease scripts.
# It only needs to be updated after major releases at the moment as
# it contains only references to the stable and security branches.

# Current dev branches (always keep master the first).
DEVBRANCHES=('master')

# Current stable branches.
STABLEBRANCHES=('MOODLE_311_STABLE' 'MOODLE_310_STABLE')

# Current security branches.
SECURITYBRANCHES=('MOODLE_39_STABLE')
