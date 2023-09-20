<?php

$CODE_ROOT = __DIR__;
$INCLUDE_PATH = $CODE_ROOT;
$INCLUDE_PATH .= PATH_SEPARATOR . $CODE_ROOT . DIRECTORY_SEPARATOR . 'lib';
$INCLUDE_PATH .= PATH_SEPARATOR . $CODE_ROOT . DIRECTORY_SEPARATOR . 'lib/CInbox';
$INCLUDE_PATH .= PATH_SEPARATOR . $CODE_ROOT . DIRECTORY_SEPARATOR . 'lib/CInbox/Task';
#$INCLUDE_PATH .= PATH_SEPARATOR . $CODE_ROOT . DIRECTORY_SEPARATOR . 'include';
#$INCLUDE_PATH .= PATH_SEPARATOR . $CODE_ROOT . DIRECTORY_SEPARATOR . 'tasks';

set_include_path(get_include_path() . PATH_SEPARATOR . $INCLUDE_PATH);


/**
 * Tries to auto-load class files, based on their $className.
 */
/* OBSOLETE. Replaced by spl_autoload_register().
function __autoload($className)
{
    $phpFile = $className .'.php';
    // echo "Autoloading '$className' ($phpFile)...\n";             // DEBUG
    include_once($phpFile);
}
 */



?>
