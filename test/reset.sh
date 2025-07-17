#!/bin/bash

BASE="/home/arkthis/archive"
ARCHIVE="${BASE}"
STAGE="${BASE}/STAGE"

# Temp:
rm -r /var/cinbox/*

# Input
rm -r todo/*
rm -r done/*
rm -r error/*
rm -r in_progress/*

# Target output:
rm -r ${ARCHIVE}/part[12]/audio/2/*
rm -r ${ARCHIVE}/part[12]/video/{E07,V,VP}/*

# Staging area:
rm -r ${STAGE}/*
mkdir ${STAGE}/part{1,2}

# Generate 1st level bucket folders:
mkdir -p ${ARCHIVE}/part1/video/{E07,V,VP}/00
mkdir -p ${ARCHIVE}/part2/video/{E07,V,VP}/00

mkdir -p ${ARCHIVE}/part1/audio/2/00
mkdir -p ${ARCHIVE}/part2/audio/2/00

# re-copy from reference example:
#cp -a _ref/e07-* todo/
cp -a _ref/v-* todo/
#cp -a _ref/_many/* todo/
