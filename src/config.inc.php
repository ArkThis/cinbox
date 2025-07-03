<?php

/**
 * This adds all necessary subfolders as include paths to allow autoload of
 * classes to work properly.
 */

$CODE_ROOT = __DIR__;
$INCLUDE_PATH = $CODE_ROOT;
$INCLUDE_PATH .= PATH_SEPARATOR . $CODE_ROOT . DIRECTORY_SEPARATOR . 'lib';                 // (more) generic re-usable code (libraries)
$INCLUDE_PATH .= PATH_SEPARATOR . $CODE_ROOT . DIRECTORY_SEPARATOR . 'lib/CInbox';          // CInbox main classes
$INCLUDE_PATH .= PATH_SEPARATOR . $CODE_ROOT . DIRECTORY_SEPARATOR . 'lib/CInbox/Task';     // CInbox Task classes

set_include_path(get_include_path() . PATH_SEPARATOR . $INCLUDE_PATH);

?>
