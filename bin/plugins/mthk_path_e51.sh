#!/bin/bash
# @author: Peter Bubestinger-Steindl
# @description
#  This script generates the output subfolder structure based on a given object-identifier.
#
#  The object ID represents a date of a radio recording:
#  E51-YYMMDDHHMM
#
#  Example:
#   E51-1501010000 => E51/15/150101/E51-1501010000
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
#
function mthk_path_e51
{
    local ARCHIVE_SIGNATURE="$1"

    local PREFIX_1=${ARCHIVE_SIGNATURE%%-*}     # Before the "-"
    local DUMMY=${ARCHIVE_SIGNATURE#*-}         # After the "-"
    local PART1=${DUMMY:0:2}                    # First 2 digits (=YY)
    local PART2=${DUMMY:0:6}                    # First 6 digits (=YYMMDD)

    MTHK_PATH="$PREFIX_1/$PART1/$PART2/$ARCHIVE_SIGNATURE"
    MTHK_PATH=${MTHK_PATH^^}            # uppercase!
    echo "$MTHK_PATH"
}


OBJID="$1"
if [ -z "$OBJID" ]; then
    # Exit unsuccessful on empty object ID:
    exit 1
fi

TARGET_SUBDIRS=$(mthk_path_e51 "$OBJID")

echo "$TARGET_SUBDIRS"
exit 0
