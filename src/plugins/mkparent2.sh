#!/bin/bash
# @author: Peter Bubestinger-Steindl
# @date: 2022-03-20
# @description:
#   Creates a parent folder of a given directory string, 
#   and (optionally) the levels of subfolders to remove
#   from right-to-left.
#
#   Examples:
#     $0 "my/parent/path"             => "my/parent/path"
#     $0 "my/deep/parent/path/is" 2   => "my/deep/parent"
#
# @params
#   $1: Folder to derive parent from.
#   $2: Number of parent levels to "go up" (=cut off)
#       Default is 1.

DIR="$1"
LIMIT=${2:-1}

# Iterate from 1 to $LIMIT and remove one subfolder level each time:
I=1
while [ $I -le $LIMIT ]; do
    DIR="$(dirname $DIR)"
    ((I++))
done

if [ -z "$DIR" ]; then
    echo "ERROR: No folder name given."
    exit 1
fi

echo ""
echo "Creating folder '$DIR'..."
echo "" 

mkdir -p "$DIR"
EXIT_CODE=$?

# Return the success/error status from "mkdir":
exit $EXIT_CODE

