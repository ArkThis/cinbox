#!/bin/bash

echo ""
echo "This is sample PRE-processor for CInbox."
echo ""

ARGS="$*"
I=0
echo "Arguments given:"
for ARG in $ARGS; do
    I=$(($I + 1))
    echo " [$I]: $ARG"
done

EXIT_CODE=$(( RANDOM % 1 ))
echo "Returning random exit code: $EXIT_CODE"
echo ""

exit $EXIT_CODE

