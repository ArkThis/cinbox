#!/bin/bash

# @author: Peter B.
# @description:
#   Install and handle pre-requisites and installed packages for CInbox to
#   setup and function correctly.


# Required packages:
# =======================
# coreutils is needed for `cut` command. Should be present out-of-the-box,
# mostly. ;) Thx GNU!
PACKAGES="git php-cli composer coreutils"
sudo apt install $PACKAGES

# These programs need to be run to prepare composer autoload and dependency handling:
# This is /NOT/ run as root, but as the user you'll be running the CInbox with daily.
composer install

# ------------------ This may be OBSOLETE?
# Pull required dependency for parsing cron-like syntax:
# Disabled here, because it should rather be pulled by composer's config file for this project.
#
# See: `composer.json`
#composer require dragonmantank/cron-expression
