#!/bin/bash
# @author: Peter Bubestinger-Steindl
# See objid_to_path-fes_dat.sh

##
# Extracts the first 2 digits from a video itemID as bucket-folder.
#
function get_path
{
    local ITEM_ID="$1"

    local INDEX=${ITEM_ID##*-}   # shortest match before '-' from back of string.
    local BUCKET1=${INDEX:0:2}  # first 2 numbers of $INDEX

    local OUT_PATH="$BUCKET1/$ITEM_ID"
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
