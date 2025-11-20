#!/bin/bash
# @author: Peter Bubestinger-Steindl
#
#  This script generates a target output subfolder structure based on a given
#  ItemID (="Object Identifier")
#
#  IMPORTANT:
#  Nothing else must be output/printed on STDOUT by this script,
#  except the final target subfolder structure as string.
#
#  Example:
#   A0211-DAT-0001 => A02/A0211-DAT-0001
#
#  Return values must be provided as follows:
#    * Success: EXIT_CODE = 0
#    * Error:   EXIT_CODE > 0
#
#  On error, the output of this script will be presented to the caller.
#  Therefore, it is possible to provide feedback about the problem to the user.


##
# Splits a given item ID and returns the final "bucketized" path.
#
function get_path
{
    local ITEM_ID="$1"

    local PREFIX_1=${ITEM_ID:0:3}   # the first 3 characters
    local OUT_PATH="$PREFIX_1/$ITEM_ID"
    OUT_PATH=${OUT_PATH^^}          # uppercase!

    echo "$OUT_PATH"
}


ITEM_ID="$1"
if [ -z "$ITEM_ID" ]; then
    # Exit unsuccessful on empty ItemID:
    echo "ERROR: Empty ItemID given."
    exit 1
fi

echo $(get_path "$ITEM_ID")
exit 0
