#!/bin/bash

# @description:
# This is a little helper for development and setup-testing.
# It quickly cleans CInbox' processing (input) and archival (output) folders,
# as well as its staging area.
# And at the end copies testing (="reference") data

# TODO:
# Currently there are still a few things hardcoded in here, for certain setups.
# Fix this and make it available as parameters.

# All relevant folders:
BASE="/home/arkthis"
CINBOX="$BASE/cinbox-data"
ARCHIVE="$BASE/archive"
STAGE="$ARCHIVE/STAGE"
REFERENCE="$CINBOX/_ref"
TEMP="/var/cinbox"

# These patterns don't work yet when used in variables:
SIG_VIDEO="{E07,V,VP}"
SIG_AUDIO="{2,22}"

echo "Folders for CInbox:"
echo "---------------------"
echo "Base:      $BASE"
echo "Data:      $CINBOX"
echo "Archive:   $ARCHIVE"
echo "Staging:   $STAGE"
echo ""
echo "Reference: $REFERENCE"
echo "Temp:      $TEMP"
echo "---------------------"
echo ""
echo "WARNING: This will delete most-if-not-everything in 'data', 'archive' and 'staging' folders."
echo ""
#read -p "Press any key to continue..."

# Temp:
rm -r $TEMP/*

# Input
rm -r $CINBOX/todo/*
rm -r $CINBOX/done/*
rm -r $CINBOX/error/*
rm -r $CINBOX/in_progress/*

# Target output:
rm -r $ARCHIVE/part[12]/audio/*
rm -r $ARCHIVE/part[12]/video/*
rm -r $ARCHIVE/part[12]/film/*

# Staging area:
rm -r $STAGE/*
mkdir $STAGE/part{1,2}

# Generate 1st level bucket folders:
mkdir -p ${ARCHIVE}/part1/video/{E07,V,VP}/00
mkdir -p ${ARCHIVE}/part2/video/{E07,V,VP}/00

mkdir -p ${ARCHIVE}/part1/audio/2/00
mkdir -p ${ARCHIVE}/part2/audio/2/00

COPY="cp -a"
# re-copy from reference example:
#$COPY $REFERENCE/e07-* todo/
#$COPY $REFERENCE/v-* todo/
#$COPY $REFERENCE/_many/* todo/

#$COPY $REFERENCE/FES.de/audio/DAT/* todo/
#$COPY $REFERENCE/FES.de/audio/MC/* todo/
#$COPY $REFERENCE/FES.de/audio/TB/* todo/

#$COPY $REFERENCE/FES.de/film/* todo/
#$COPY $REFERENCE/FES.de/video/VHS/* todo/
#$COPY $REFERENCE/FES.de/video/Umatic/* todo/
#$COPY $REFERENCE/FES.de/video/Digibeta/* todo/

