#!/bin/bash
#
# This is a pre-processor script to prepare files from an s3 storage 
# to be processed by the Common-Inbox. 
#
# Parameters:
# $1 = File containing s3 listing
# 
#

# Configuration
S3_SOURCE_LIST="$1"
MIN_DISK_SPACE=350 	# in GB
INBOX_ROOT="/mnt/inbox"
TEMP_DIR="/tmp"
TEMP_FILE="$TEMP_DIR/XXXXXXXXXX.s3preproc"
ITEMID_PREFIX="DV-"
RETRIES=2

# Other variables
S3_PROC_DIR="$INBOX_ROOT/s3_processing"
S3_PREP_LIST=$(mktemp $TEMP_FILE)
S3_TODO_LIST=$(mktemp $TEMP_FILE)


# Let's go!
echo "INIT: Initializing & sanity checks..."

# Check if enough disk space is free and abort if not
SPACE_AVAILABLE=`df $INBOX_ROOT | awk 'NR==2 {print $4}' | awk '{print $1/1000000}' | awk '{print int($1+0.5)}'`
if [ "$SPACE_AVAILABLE" -le "$MIN_DISK_SPACE" ] ; then
  echo "ERROR: There are only $SPACE_AVAILABLE GB available, but a minimum of $MIN_DISK_SPACE is required to continue. Doing nothing this cycle and gracefully exit." >&2
  exit 0
else 
  echo "INIT: Disk space required / available: $MIN_DISK_SPACE G / $SPACE_AVAILABLE G - OK!" 
fi


# Check / Create directory structure in Inbox folder
if [ ! -d $S3_PROC_DIR ]; then
	echo "INIT: '$S3_PROC_DIR' does not exist, trying to create it..."
	mkdir -p $S3_PROC_DIR;
		  if [ $? -ne 0 ] ; then
			echo "ERROR: Could not create directory!"; exit 1;
		  else
			echo "INIT: Directory created successfully!"
		  fi
	else
		echo "INIT: '$S3_PROC_DIR' exists - OK!"  
fi


# Check if S3_SOURCE_LIST exists
if test -f "$S3_SOURCE_LIST"; then
	echo "INIT: '$S3_SOURCE_LIST' exists - OK!"
else
	echo "ERROR: Could not access s3 list file '$S3_SOURCE_LIST'! Was an argument passed?"; exit 1;
fi

# Check if STATEFILE for this S3_SOURCE_FILE exists
S3_SOURCE_FILE=`basename -- "$S3_SOURCE_LIST"`
STATEFILE="$S3_PROC_DIR/$S3_SOURCE_FILE.statefile"
if test -f "$STATEFILE"; then
        echo "INIT: '$STATEFILE' for '$S3_SOURCE_LIST' exists - OK!"
else
        echo "INIT: No '$STATEFILE' found, it will be created this run."
fi

# Grab the input s3 listing and crop stuff we dont use
cat $S3_SOURCE_LIST | sed 's/^.*s3\:/s3\:/' >> $S3_PREP_LIST

# Display configured ITEMID_PREFIX
echo "INIT: Using '$ITEMID_PREFIX' as Item Prefix. "

# Use line numbers as counter (with leading zeroes) and add them to the list
nl -nrz $S3_PREP_LIST >> $S3_TODO_LIST
echo "INIT: Complete."
echo "------------------------------------------------------------"; echo;

# Read each line and create files with s3 information and ItemID, then
# create Item directory and copy .s3info file there. Also check if 
# it is a re-run of the script and if  the input file has already been 
# processed, thus Item directories already exist.

while read line; do
  TODO_TEMP=$(mktemp $TEMP_FILE)
  S3_ITEMID=$(echo $line | cut -d' ' -f1)
  S3_BUCKETID=$(echo $line | cut -d'/' -f3)
  S3_OBJECTID=$(echo $line | cut -d'/' -f4-)
    
  echo "INFO: Processing Item '$ITEMID_PREFIX$S3_ITEMID'..."
  echo "S3_ITEMID='$ITEMID_PREFIX$S3_ITEMID'" >> $TODO_TEMP
  echo "S3_BUCKETID='$S3_BUCKETID'" >> $TODO_TEMP
  echo "S3_OBJECTID='$S3_OBJECTID'" >> $TODO_TEMP
 
  if [ ! -d $S3_PROC_DIR/$ITEMID_PREFIX$S3_ITEMID ]; then
        echo "INFO: Creating Item directory '$S3_PROC_DIR/$ITEMID_PREFIX$S3_ITEMID'..."
	mkdir -p $S3_PROC_DIR/$ITEMID_PREFIX$S3_ITEMID/{archive,access,mezzanine}
	echo "INFO: Writing Item's s3info file..."
	cp $TODO_TEMP $S3_PROC_DIR/$ITEMID_PREFIX$S3_ITEMID/$ITEMID_PREFIX$S3_ITEMID.s3info
	rm $TODO_TEMP
  else
   	echo "INFO: Item directory '$S3_PROC_DIR/$ITEMID_PREFIX$S3_ITEMID' already exists, skipping pre-processing..."
  	rm $TODO_TEMP
  fi
done < $S3_TODO_LIST
echo "INFO: Preparation complete."


# Start processing Items, get information from s3info file
for item in $S3_PROC_DIR/$ITEMID_PREFIX* ; do
    echo " "
    echo "------------------------------------------------------------"
    echo "INFO: Preparing Item '$item' ..."
    echo "INFO: Fetching information from s3info file..."
    S3INFOFILE=$(echo ${item##*/}).s3info
    S3_ITEMID="$(cat "$item/$S3INFOFILE" | grep S3_ITEMID | cut -d\' -f2 )"
    S3_BUCKETID="$(cat "$item/$S3INFOFILE" | grep S3_BUCKETID | cut -d\' -f2 )"
    S3_OBJECTID="$(cat "$item/$S3INFOFILE" | grep S3_OBJECTID | cut -d\' -f2 )"
    S3_OBJECTEXTENSION="$(echo "$S3_OBJECTID" | cut -d'.' -f2)"

# Check $STATEFILE if the object was already processed and might just have
# been moved to the Common Inbox ToDo folder...
    echo "INFO: Checking if object has already been handled in '$STATEFILE'..."
    if grep -qF "$S3_OBJECTID" "$STATEFILE" 2>/dev/null; then
        echo "INFO: Object already processed, skipping."
        continue 2
    else
        echo "INFO: No known state for Object, continue workflow..."
    fi
    

# Check if hash line already exists in s3info file to prevent multiple entries
# (Also check for empty value? Catch/retry if s3cmd fails?)
    echo "INFO: Check if hash already stored in s3info file..."
    if grep -q 'S3_OBJECTHASH=' $item/$S3INFOFILE; then
        echo "INFO: Object hash already exists."
    else 
        echo "INFO: Fetching hash from s3 storage and writing it to s3info file..."
        S3_OBJECTHASH="$(s3cmd ls --list-md5 "s3://$S3_BUCKETID/$S3_OBJECTID" 2>/dev/null | tr -s " " | cut -d' ' -f4 )"
        echo "S3_OBJECTHASH='$S3_OBJECTHASH'" >> $item/$S3INFOFILE;
    fi

# Check if Item is already marked as "ready for Inbox"...
    echo "INFO: Checking status of Item..."
    if [ -f "$item/ready" ]; then
        echo "INFO: '$item/ready' exists, Item appears finished. Skipping."
        continue 2
    else
        echo "INFO: No '$item/ready' file found. Proceeding..."
    fi

# Check if media file of Item already exists and if it has a not-null filesize
    echo "INFO: Checking if Item media already exists..."
    if [ -f "$item/$S3_ITEMID.$S3_OBJECTEXTENSION" ]; then
	    echo "INFO: '$item/$S3_ITEMID.$S3_OBJECTEXTENSION' already exists, checking size..."
	    S3OBJECTSIZE="$(s3cmd ls "s3://$S3_BUCKETID/$S3_OBJECTID" 2>/dev/null | tr -s " " | cut -d' ' -f3 )"
	    LOCALOBJECTSIZE="$(ls -la "$item/$S3_ITEMID.$S3_OBJECTEXTENSION"| tr -s " " | cut -d' ' -f5)"
	    if [ -z "$S3OBJECTSIZE" ]
		then
		      echo "'$S3OBJECTSIZE'"
		      echo "ERROR: s3 object size zero? Skipping..."
		      continue 2
		else
		      echo "INFO: s3 object size appears valid..."
            fi

# Compare media filesize with reported filesize from s3 storage
    if [ "$S3OBJECTSIZE" == "$LOCALOBJECTSIZE" ]; then
    	echo "INFO: Filesizes match - OK. Leaving 'ready' file... "
    	touch "$item/ready"
        echo "INFO: Updating $STATEFILE ..."
        echo $S3_OBJECTID >> $STATEFILE;
        echo "INFO: '$item' - PreProcessing complete."
        # copy ready Item to target $INBOX_ROOT/todo automatically?
    else
        echo "ERROR: Filesize mismatch: '$S3OBJECTSIZE' / '$LOCALOBJECTSIZE'. Corrupt/partial download?"
        echo "INFO: Deleting left-overs for clean retry on next run..."
        rm "$item/$S3_ITEMID.$S3_OBJECTEXTENSION" &>/dev/null
        continue
    fi

    continue
    fi
    
    
    # Actually try to download the Item if checks passed. Sometimes
    # the s3 servers can be a bit flakey with peer resets, thus  we
    # run n $RETRIES for a higher chance of success.
    S3_LOCALFILE=`echo "$S3_OBJECTID" | rev | cut -d"/" -f1 | rev`
    S3_RENAMEFILE=`echo $item/$S3_ITEMID.$S3_OBJECTEXTENSION`
    i=`echo $RETRIES`
    echo "INFO: Downloading $item..."
    #until s3cmd get "s3://$S3_BUCKETID/$S3_OBJECTID" "$item/$S3_ITEMID.$S3_OBJECTEXTENSION" 2>/dev/null; do
    until s3cmd get "s3://$S3_BUCKETID/$S3_OBJECTID" "$item/" ; do
          sleep `expr $RANDOM % 20`
  	  i=$(( i-1 ))  
       		 if [ $i -ne 0 ]; then
       	 		echo "ERROR: Retrieving Object '$S3_ITEMID' from s3 storage failed after $RETRIES retries - skipping!"
      	 		echo "INFO: Deleting left-overs for clean retry on next run..."
       	 		rm "$item/$S3_LOCALFILE" &>/dev/null
       	 		continue 2
  		 fi
     done

    # Verify the downloaded file by trying to sync it with the server to force
    # a "etag" compare
        s3cmd sync "s3://$S3_BUCKETID/$S3_OBJECTID" "$item/" -v
   	 if [ $? -ne 0 ]; then
   	    echo "ERROR: Verifying Object '$S3_ITEMID' with s3 storage etag failed."
            echo "INFO: Deleting left-overs for clean retry on next run..."
            rm "$item/$S3_LOCALFILE" &>/dev/null
            continue 2
   	 else 
   	    echo "INFO: File verified. Renaming to '$S3_RENAMEFILE'..."
   	    mv "$item/$S3_LOCALFILE" "$S3_RENAMEFILE"
   	 fi

     echo "INFO: Download and verification complete! Leaving 'ready' file..."
     touch "$item/ready"
     echo "INFO: Updating $STATEFILE ..."
     echo $S3_OBJECTID >> $STATEFILE;
     echo "INFO: '$item' - PreProcessing complete!."
     # copy ready Item to target $INBOX_ROOT/todo automatically?
done 
echo " "; echo " "; echo "INFO: All Items from source file '$S3_SOURCE_LIST' have been processed ♥"     


# Clean up on aisle five
rm $S3_PREP_LIST $S3_TODO_LIST $TODO_TEMP 2>/dev/null;


