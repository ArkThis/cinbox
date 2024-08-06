<?php

namespace ArkThis\CInbox;

/*
 * TODO:
 * Have task-specific placeholders be added by a new task rather than here.
 */

// Stringmask placeholders:

define('__ITEM_ID__', '[@ITEM_ID@]');           // Item ID = Object identifier = archive signature
define('__ITEM_ID_UC__', '[@ITEM_ID_UC@]');     // Item ID. UpperCase.
define('__ITEM_ID_LC__', '[@ITEM_ID_LC@]');     // Item ID. LowerCase.
define('__BUCKET__', '[@BUCKET@]');             // "bucketized" subfolder structure in archive

// Date / time:
define('__YEAR__', '[@YEAR@]');
define('__MONTH__', '[@MONTH@]');
define('__DAY__', '[@DAY@]');
define('__HOUR__', '[@HOUR@]');
define('__MINUTE__', '[@MINUTE@]');
define('__SECOND__', '[@SECOND@]');

define('__DATETIME__', '[@DATETIME@]');


define('__PHP_SELF__', '[@PHP_SELF@]');             // Value of $_SERVER['PHP_SELF']. See: https://www.php.net/manual/en/reserved.variables.server.php
define('__PHP_SELF_DIR__', '[@PHP_SELF_DIR@]');     // Path of PHP_SELF.
define('__PHP_SELF_NAME__', '[@PHP_SELF_NAME@]');   // Filename of PHP_SELF.

define('__DIR_IN__', '[@DIR_IN@]');                 // Path of input file
define('__FILE_IN__', '[@FILE_IN@]');               // A filename (in/source)
define('__FILE_IN_NOEXT__', '[@FILE_IN_NOEXT@]');   // FILE_IN without its suffix/extension

define('__DIR_OUT__', '[@DIR_OUT@]');               // Path of output file
define('__FILE_OUT__', '[@FILE_OUT@]');             // A filename (out/target)
define('__FILE_OUT_NOEXT__', '[@FILE_OUT_NOEXT@]'); // FILE_OUT without its suffix/extension

define('__LOGFILE__', '[@LOGFILE@]');               // Name of a logfile


define('__OPTIONS__', '[@OPTIONS@]');               // Options/arguments (provided to e.g. external commands)

define('__HASHTYPE__', '[@HASHTYPE@]');             // A hashcode type (md5, sha1, crc, etc)
define('__HASHCODE__', '[@HASHCODE@]');             // A hashcode
define('__FILENAME__', '[@FILENAME@]');             // A Filename (without path)

define('__DIR_TARGET__', '[@DIR_TARGET@]');         // Item subfolder: target folder
define('__DIR_SOURCE__', '[@DIR_SOURCE@]');         // Item subfolder: source folder
define('__DIR_BASE__', '[@DIR_BASE@]');             // Item base folder
define('__DIR_TEMP__', '[@DIR_TEMP@]');             // Item temp folder
define('__DIR_TARGET_STAGE__', '[@DIR_TARGET_STAGE@]');         // Item subfolder: target folder


define('__TASK_NAME__', '[@TASK_NAME@]');           // Classname of task
define('__TASK_LABEL__', '[@TASK_LABEL@]');         // Label of task


?>
