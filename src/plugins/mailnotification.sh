#!/bin/bash
# @author: Thomas Schieder
# @description
#  This script sends out a notification mail as post-processing step
#  of the Common-Inbox. The subject contains the ItemID, the message
#  body the Items logfile. Requires mailutils package to be installed
#  and a minimum functional MTA. The script will try to autodetect
#  the mail send application - if this does not work, set $MAILER manually.
#
#  Required arguments:
#   $1 = ItemID - should be passed over from CIN via the [@ITEM_ID@] variable.
#   $2 = Email address(es) - supports multiple by separating with a coma.
#   $3 = log path - Inbox log folder.
#   $4 = Sender name (Useful for multiple Inboxes). No whitespaces or
#        specíal chars allowed.
#
#  Config line example:
#   POSTPROCS[] = /home/cin/cinbox/bin/plugins/mailnotification.sh "[@ITEM_ID@]" "email@your.tld,email2@your.tld" "/mnt/inbox/log" "Common-Inbox_1"
#
#  ToDo:
#       * Better way to get log directory ($3)?
#       * CIN writes logs lower-case, so Item CD-123456 log file becomes 'cd-123456.log'. Requires workaround.

echo; echo "E-mail notification Plug-in for Common-Inbox"; echo;

# Check if we get the correct number of arguments
if [ $# -ne 4 ]
  then
    echo "Wrong number of arguments supplied, exiting..."
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

# Check pre-requisite: 'mail' installed
MAILER=`which mail`
if command -v $MAILER >/dev/null; then
 echo; echo "$MAILER found: Good!"
else
 echo; echo "Cannot find $MAILER or permission issue. Is the mailutils package installed?"; echo;
 exit 1
fi

# Workaround: Force lower-case, e.g. CD-123456 -> cd-123456 to match CIN log naming (should be fixed?)
LOGNAME=`echo $1  | tr '[:upper:]' '[:lower:]'`

# Send a mail from the Inbox with subject "[@ItemID@] processed" to set email, task log in mail body.
echo; echo "Sending mail from $4 about Item $1 to $2, with content of logfile $3/$LOGNAME ..."; echo;
$MAILER -s "$1 processed" $2 -aFrom:"$4" < $3/$LOGNAME.log

EXIT_CODE=$?
echo "Returning exit code: $EXIT_CODE"; echo;

exit $EXIT_CODE

