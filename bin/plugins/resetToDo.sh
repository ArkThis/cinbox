#!/bin/bash

echo ""
echo "This script marks a task status 'WAIT' and triggers the item to be reset to its to-do folder"
echo ""

ARGS="$*"
I=0
echo "Arguments given:"
for ARG in $ARGS; do
    I=$(($I + 1))
    echo " [$I]: $ARG"
done

EXIT_CODE=5         # 5 = Task STATUS_WAIT
echo "Returning exit code: $EXIT_CODE"
echo ""

exit $EXIT_CODE

