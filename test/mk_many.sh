#!/bin/bash

# @author: Peter B.
# @date: 2024-03
# @description:
# Generates a(n arbitrarily) large number of dummy Items to test CInbox.
# Set the value of "MAX" to the maximum of Items to create.
# This was used to debug and fix 
# [issue #20: "Too many open files" when having ~1070 entries in "done" folder](https://github.com/ArkThis/cinbox/issues/20)

ID_MASK="vp-%05d"       # Mask of Item identifier.
MY_DIR=$(pwd)           # Current working folder of script.
DIR_OUT="_many"         # Output folder where generated Items will be written.

MAX=7                   # <- Change THIS to set how many Items you want to generate.
ITEM_GENERATOR="$MY_DIR/mk_video.sh"    # Script that creates a stub item.

if [ ! -d "$DIR_OUT" ]; then
    echo "ERROR: Output folder '$DIR_OUT' does not exist yet. Please create it."
    exit 1
fi

cd $DIR_OUT

echo "Generating stub items..."
for i in $(seq 1 $MAX); do
    ITEMID=$(printf "${ID_MASK}" $i)

    # If you're doing hundreds or thousands items, you may want to comment this
    # out, as this output actually slows down the process a little bit:
    echo "Item: $ITEMID"

    # Create the Item stub:
    $ITEM_GENERATOR "$ITEMID"

    # This code is used to create dummy AVI files:
    # (instead of using $ITEM_GENERATOR script.)
    #STUB=$DIR_HIRES/$(printf "${ITEMID}_%03d.avi" $i)
    #echo -n "$i, "
    #dd if=/dev/urandom bs=512k count=2 status=none of=$STUB
done

