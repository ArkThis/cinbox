#!/bin/bash
# @description:
#  Reads .s3info file and reproduces original filename structure.


# Valid return values:
EXIT_STATUS_DONE=0;                 # Success! This means the task completed successfully.
EXIT_STATUS_WAIT=5;                 # Task decided that Item is not ready yet and shall be moved back to 'to-do'.
EXIT_STATUS_PBCT=6;                 # There were problems, but the task may continue.
EXIT_STATUS_PBC=7;                  # There were problems, but subsequent task may continue.
EXIT_STATUS_ERROR=10;               # An error occurred. Abort execution as soon as possible.
EXIT_STATUS_CONFIG_ERROR=11;        # If a config option was not valid
EXIT_STATUS_SKIPPED=15;             # If task was skipped

# --- Global variables:
TARGET_DIR=""
TARGET_FILENAME=""

# --- Arguments from commandline:
ITEM_ID="$1"
DIR_SOURCE="$2"
S3_INFOFILE="$3"

##
# Read the .s3info file and load its contents into BASH variables.
#
function readS3InfoFile
{
    local S3_INFOFILE="$1"

    echo "Reading S3 info file: '$S3_INFOFILE'..."

    source "$S3_INFOFILE"

    echo ""
    echo "S3 Infos:"
    echo "  Item ID: $S3_ITEMID"
    echo "  Bucket ID: $S3_BUCKETID"
    echo "  Object ID: $S3_OBJECTID"
    echo ""
    echo ""
}


##
# Extract target subfolder and filename from S3 ObjectID.
#
function getTargetNames
{
    local S3_OBJID="$1"

    local DIR=$(dirname "${S3_OBJID#*/}")       # Get path without first base folder.
    local BASE=$(basename "$S3_OBJID")          # Get filename (with extension).
    local FILENAME="${BASE%.*}"                 # Then strip the file extension.

    TARGET_DIR="$DIR"
    TARGET_FILENAME="$FILENAME"
}


##
# Renames all files in the given folder to replace the ItemID with the
# filename retrieved from $S3_OBJECTID.
#
function renameFiles
{
    local DIR="$1"
    local ITEM_ID="$2"
    local FILENAME="$3"

    # Change to that folder, to avoid replacing ItemID in path generally:
    cd "$DIR"
    CMD="rename 's/$ITEM_ID/$FILENAME/' $ITEM_ID*.*"
    echo "$CMD"
    eval "$CMD"

    # Return exitcode from rename call:
    return $?
}



# =====================================================================

echo "Item ID: $ITEM_ID"
echo "Folder: $DIR_SOURCE"
echo "S3 info file: $S3_INFOFILE"

readS3InfoFile "$S3_INFOFILE"
getTargetNames "$S3_OBJECTID"
renameFiles "$DIR_SOURCE" "$ITEM_ID" "$TARGET_FILENAME"
EXIT_CODE=$?

if [ $EXIT_CODE -ne 0 ]; then
   return $EXIT_STATUS_ERROR
fi

exit $EXIT_STATUS_DONE

