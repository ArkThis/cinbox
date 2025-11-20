#!/bin/bash
#@date: 2025-11-20
# General, common preprocessing for FES workflows.

# Parameter: The filename of the common file, and the signature to copy it into.
DIR_BASE="$1"
COMMON_MASK="$2"

echo "-------------------------------"
echo " FES generic preprocessor"
echo "-------------------------------"
echo ""
echo "- Removing unwanted files (Thumbs.db, etc)."
echo "- including 'common' files outside of Item dir for processing."
echo ""


# This is only used for debugging:
function debug ()
{
    echo ""
    echo "ITEM base dir: $DIR_BASE"
    echo "Common assets: $COMMON_MASK"
    read -p "press return"
    echo ""
}
#debug  # uncomment to enable debugging.


# Sanity check: can we access the given BASE folder?
# --------------------------------------------
if [ -z "$DIR_BASE" ]; then
    echo "ERROR: Item base folder not found: '$DIR_BASE'"
    exit 1
fi


# Remove "File Noise":
# --------------------------------------------
DEL_THUMBS="$DIR_BASE/Thumbs.db"
if [ -e "$DEL_THUMBS" ]; then
    echo "Removing '$DEL_THUMBS'..."
    rm $DEL_THUMBS
    RESULT=$?

    if [ $RESULT -ne 0 ]; then
        echo "ERROR: removal of '$DEL_THUMBS' failed with exit code '$RESULT'."
        exit $RESULT
    fi
fi


# Copy common stuff into the item's folder for easier processing and use:
# --------------------------------------------
echo "Copying common assets over into item path..."
cp -av $COMMON_MASK $DIR_BASE/
RESULT=$?

if [ $RESULT -ne 0 ]; then
    echo "ERROR: Copying of common assets '$COMMON_MASK' failed with exit code '$RESULT'."
    exit $RESULT
fi


# TODO (optional):
# Create common subfolders for an item?
# --------------------------------------------
# mkdir $DIR_BASE/{metadata,access}
# RESULT=$?

echo "FES general preproc: All good."
EXIT_CODE=0

exit $EXIT_CODE


