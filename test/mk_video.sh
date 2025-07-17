#!/bin/bash

# @author: Peter B.
# @started: 2023-09-22
# @description:
# Generates a test-stub set for a video ingest package.
# The video files are complete dummies (random bytes).

ITEMID="$1"

DIR_HIRES="$ITEMID/hires"
DIR_LOWRES="$ITEMID/lowres"
DIR_METADATA="$ITEMID/metadata"

STUBSIZE="16k"

if [ -z "$ITEMID" ]; then
    echo "ERROR: No Item-ID given."
    echo ""
    echo "SYNTAX:  $0 ITEMID"
    echo "Example: $0 vx-00815"
    exit 1
fi

echo "Creating Item folders..."
mkdir -v $ITEMID            # One parent folder per-id
mkdir -v $DIR_HIRES         # archival copy
mkdir -v $DIR_LOWRES        # access copy
mkdir -v $DIR_METADATA      # all things metadata
mkdir -p $DIR_METADATA/images/minutes
mkdir -p $DIR_METADATA/images/scene-cuts

# Uncomment this to pause before creating anything:
read -p "Press any key to continue..."

echo "Generating video stub files..."
for i in $(seq 0 10); do
    STUB=$DIR_HIRES/$(printf "${ITEMID}_%03d.avi" $i)
    echo -n "$i, "
    dd if=/dev/urandom bs=$STUBSIZE count=2 status=none of=$STUB
done

echo
echo "Generating lowres copy..."
VIDEO_LOWRES="$DIR_LOWRES/${ITEMID}_k01.mpg"
dd if=/dev/urandom bs=$STUBSIZE count=2 status=none of=$VIDEO_LOWRES
