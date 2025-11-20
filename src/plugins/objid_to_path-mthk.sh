#!/bin/bash
# @author: Peter Bubestinger-Steindl
# @description
#  This script generates the output subfolder structure based on a given object-identifier.
#
#  Example:
#   VX-00815 => VX/00/VX-00815
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

##
# Splits a given archive signature and returns the final
# bucketized path.
# Example:
#   Signature = "vx-012345"
#   MTHK_PATH = "VX/01/VX-012345"
#
function mthk_path
{
    local ARCHIVE_SIGNATURE="$1"

    local PREFIX_1=${ARCHIVE_SIGNATURE%%-*}
    local DUMMY=${ARCHIVE_SIGNATURE#*-}
    local PREFIX_2=${DUMMY:0:2}

    MTHK_PATH="$PREFIX_1/$PREFIX_2/$ARCHIVE_SIGNATURE"
    MTHK_PATH=${MTHK_PATH^^}            # uppercase!
    echo "$MTHK_PATH"
}


OBJID="$1"
if [ -z "$OBJID" ]; then
    # Exit unsuccessful on empty object ID:
    exit 1
fi

TARGET_SUBDIRS=$(mthk_path "$OBJID")

echo "$TARGET_SUBDIRS"
exit 0
