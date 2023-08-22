#!/bin/bash
# @author: Thomas Schieder
# @description
#   This Plug-In for Common Inbox allows execution of MySQL commands.
#   Requires the mysql package to be installed. Use the
#   "Configuration Block" to configure the database credentials and
#   build your SQL statement(s) in the "Start SQL" section.
#
#  Arguments:
#   At least one argument must be supplied. If more are required,
#   add the mapping in the "Variable<>Argument translation" section.
#   The example code uses $1 as ITEMID as passed from CIN via
#   the [@ITEMID@] variable.
#
#  Config line example:
#   POSTPROCS[] = /home/cin/cinbox/bin/plugins/executemysql.sh "[@ITEM_ID@]"
#

echo; echo "Execute MySQL Plug-In for Common-Inbox"; echo;

# Configuration Block
DBHOST=localhost
DBUSER=cin
DBPASS=cin
DBNAME=archivedb

# Check if we get the minimum required arguments (1)
if [ $# -ne 1 ]
  then
    echo "Not enough arguments supplied, exiting..."
    exit 1
fi

# List the supplied arguments (useful for debugging)
ARGS="$*"
I=0
echo "Arguments given:"
for ARG in $ARGS; do
    I=$(($I + 1))
    echo " [$I]: $ARG"
done

# Variable<>Argument translation
ITEMID=$1
#ARG2=$2
#ARG3=$3


# Check pre-requisite: 'mysql' installed
MYSQLBIN=`which mysql`
if command -v $MYSQLBIN >/dev/null; then
 echo; echo "$MYSQLBIN found: Good!"
else
 echo; echo "Cannot find $MYSQLBIN or permission issue. Is the mysql package installed?"; echo;
 exit 1
fi

#  Create temp file and build SQL
echo; echo "Creating temp file $TEMPSQLFILE..."; echo;
TEMPSQLFILE=`mktemp`

# Start SQL - echo lines into temp file
echo; echo "Adding SQL lines to $TEMPSQLFILE..."; echo;
echo "INSERT INTO Items VALUES ($ITEMID);" >> $TEMPSQLFILE;
echo "COMMIT;" >> $TEMPSQLFILE;
# End SQL

#Connect to MySQL server and execute sql from temp file
echo; echo "Connecting to MySQL server and executing $TEMPSQLFILE with content:"; echo;
cat $TEMPSQLFILE; echo;

$MYSQLBIN -h$DBHOST -lD$DBUSER -p$DBPASS -D$DBNAME < $TEMPSQLFILE;
EXIT_CODE=$?

# Delete temporary SQL file
echo; echo "Deleting temp file $TEMPSQLFILE..."; echo;
rm $TEMPSQLFILE;

# Return outcome to Common-Inbox
echo "Returning exit code: $EXIT_CODE"; echo;
exit $EXIT_CODE


