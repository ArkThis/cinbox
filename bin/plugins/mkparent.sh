#!/bin/bash

DIR="$(dirname $1)"
if [ -z "$DIR" ]; then
    echo "ERROR: No folder name given."
    exit 1
fi

echo ""
echo "Creating folder '$DIR'..."
echo ""

mkdir -p "$DIR"
EXIT_CODE=$?

exit $EXIT_CODE

