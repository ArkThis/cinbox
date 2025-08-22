#!/bin/bash
# @author: Peter Bubestinger-Steindl
# @description
#  This script generates the output subfolder structure based on a given object-identifier.
#
#  Example:
#   A-FIL-00815 => A/FIL/00/A-FIL-00815
#
#
#  IMPORTANT: Nothing else must be output, except the target subfolder structure as string:
#
#  Return values must be provided as follows:
#    * Success: EXIT_CODE = 0
#    * Error:   EXIT_CODE > 0
#
#  On error, the output of this script will be presented to the caller.
#  Therefore, it is possible to provide feedback about the problem to the user.

CUT="cut"   # The cut command is a prerequisite.

function getCol
{
    TXT=$1
    COL=$2
    echo "$TXT" | cut -d'-' -f${COL} -      # hardcoded delimiter: '-'
}

##
# Splits a given archive signature and returns the final
# bucketized path.
# Example:
#   Signature = "A-VID-003508"
#   BUCKET = "A/VID/00/A-VID-003508"
#
function BucketStringByID
{
    local ITEM_ID="$1"

    local PREFIX_1=$(getCol "$ITEM_ID" 1)       # Get 1st column
    local PREFIX_2=$(getCol "$ITEM_ID" 2)       # Get 2nd column
    local PART_3=$(getCol "$ITEM_ID" 3)         # Get 3rd column
    local REST=$(getCol "$ITEM_ID" "4-")        # Collect the rest (column 4+, if exists) (column 4+, if exists; otherwise empty)
    local SUB_BRANCH=${PART_3:0:2}      # Take the first 2 chars (from left) as sub-dir (branch)

    BUCKET="$PREFIX_1/$PREFIX_2/$SUB_BRANCH/$ITEM_ID"
    BUCKET=${BUCKET^^}              # forced to uppercase!
    echo "$BUCKET"                  # This is not shown, but the return string of the function.
}



# ===============================================
#   MAIN
# ===============================================

ITEM_ID="$1"
if [ -z "$ITEM_ID" ]; then
    # Exit unsuccessful on empty item ID:
    exit 1
fi

# The current syntax is compatible as sub-directory structure.
# This is where the "echo" from BucketStringByID is captured.
# Therefore titled "target subdirs":
TARGET_SUBDIRS=$(BucketStringByID "$ITEM_ID")

# Now it's output:
# As return string to the calling CInbox :)
# Handover.
echo "$TARGET_SUBDIRS"

# Exit successfully and happy!
exit 0
