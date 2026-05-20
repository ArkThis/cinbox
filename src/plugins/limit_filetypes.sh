#!/bin/bash

LIMIT=$1
DIR="$2"
PATTERNS="$3"

echo ""
echo "Limiting number of files-per-type:"
echo ""
echo " - max. $LIMIT files"
echo " - in folder '$DIR'"
echo " - of types: '$PATTERNS'"
echo ""

EXIT_CODE=0

if [ ! -d "$DIR" ]; then
    echo "ERROR: Folder does not exist '$DIR'"
    exit 2
fi

# Loop through PATTERNS (must be space-separated)
for PATTERN in $PATTERNS; do
    LIST=$(ls -1 $DIR/$PATTERN)
    RESULT=$?
    COUNT=$(echo "$LIST" | wc -l)

    if [ $RESULT -eq 0 ]; then
        echo "Found $COUNT matches for '$PATTERN'."
        #echo "$LIST" # DEBUG

        if [ $COUNT -gt $LIMIT ]; then
            echo "ERROR: Found ($COUNT) exceeds limit ($LIMIT)."
            EXIT_CODE=1
        fi
    else
        echo "No match for '$PATTERN'"
    fi
    echo ""
done

exit $EXIT_CODE
