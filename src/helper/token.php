#!/usr/bin/env php
<?php

/**
 * This is a simple CLI helper to generate CInbox-compatible token files
 * with a given set of targetFolders and an (item)ID.
 */

const MEMORY_TS_FORMAT = 'Ymd-His'; 

// Exit codes:
define('EXIT_OK', 0);
define('EXIT_ERROR_ARGS', 10);
define('EXIT_ERROR_INIT', 11);
define('EXIT_ERROR_RUNTIME', 12);   // Unknown error during runtime.



/**
 * Parse the commandline arguments.
 */
function parseArgs()
{
    global $input, $folder;

    // Available commandline arguments (see showHelp() for details):
    $shortOpts = "i:f:";
    $longOpts = array(
        'input ID',             // archive signature (input)
        'target folder',        // target folder to list in the token.
    );

    $options = getopt($shortOpts, $longOpts);
    if (empty($options) || isset($options['h'])) 
    {
        // TODO: Currently there's no syntax help ;)
        return EXIT_OK;
    }

    // ------------------------------------------------
    // Evaluate and set options:

    if (isset($options['i']))
    {
        $input = $options['i'];
        #printf("Input: %s\n", $input);
    }

    if (isset($options['f']))
    {
        $folder = $options['f'];
        #printf("Output: %s\n", $folder);
    }
}


/**
 * Same as recall(), but formats the stored data in a more human-readable
 * way. Translating the unix time to a formatted date/time string, and
 * reformatting the information in a more "common" way.
 */
function recallNice($entry)
{
    // Recall entry (array) with unix-time keys:
    //$entry = $this->recall($key, $strict, $unique);
    $newEntries = array();   // Target array with "$new_key" format.

    foreach ($entry as $unixTime => $value)
    {
        $timestamp = date(MEMORY_TS_FORMAT, $unixTime);

        $newEntry = array();   // This is going out.
        // Populate it properly:
        $newEntry['timestamp'] = $timestamp;
        $newEntry['unixTime'] = $unixTime;
        $newEntry['value'] = $value;

        $newEntries[] = $newEntry;
    }

    return $newEntries;
}


/**
 * Returns a parse-able text that contains all desired information to be
 * written in an item's "token" file for inter-process communication.
 *
 * @param[in] flags    See: https://www.php.net/manual/en/json.constants.php
 */
function getTokenData($flags=null, $itemId=null, $targetFolders=null)
{
    // TODO/FIXME:
    // There should be a different variable for this, than just dumping the
    // item's "memory" here...
    //

    $tokenData = array();
    $tokenData['itemId'] = $itemId;
    $tokenData['targetFolders'] = recallNice($targetFolders);

    $json = json_encode($tokenData, $flags);

    return $json;
}


/** ===========================================
 *     MAIN
 * ===========================================
 */


$input = null;
$folder = null;

parseArgs();

$targetFolders = array(
    time() => $folder
);

$token = getTokenData(null, $input, $targetFolders);
printf("%s\n", $token);


?>

