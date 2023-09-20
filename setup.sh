#!/bin/bash
#@description:
# These programs need to be run to prepare composer autoload and dependency handling:

composer install

# Pull required dependency for parsing cron-like syntax:
#composer require dragonmantank/cron-expression
