#!/bin/bash

EXIT_CODE=0

echo ""
echo "This is sample script for CInbox."
echo "It simply outputs the commandline arguments which it was called with."
echo ""
echo "Returns exit code: $EXIT_CODE"
echo ""

ARGS="$*"
I=0
echo "Arguments given:"
for ARG in $ARGS; do
    I=$(($I + 1))
    echo " [$I]: $ARG"
done

exit $EXIT_CODE

