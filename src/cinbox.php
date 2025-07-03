#!/usr/bin/env php
<?php

require_once('config.inc.php');
require_once('version.inc.php');

require __DIR__.'/../vendor/autoload.php';

use ArkThis\Logger;
use ArkThis\CInbox\CInbox;
use ArkThis\CInbox\CIConfig;


/**
 * @brief
 * This is the main executable.
 * This is where the Common Inbox is started from as commandline application.
 *
 * @detail
 *  No details yet.
 *
 * @author Peter Bubestinger-Steindl (cinbox (at) ArkThis.com)
 * @copyright
 *  Copyright 2025 ArkThis AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.ArkThis.com/products/cinbox/">CInbox product website</a>
 *  - <a href="https://github.com/ArkThis/cinbox/">CInbox source code</a>
 *  - <a href="http://www.ArkThis.com/">ArkThis.company Website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 */

/* ========================================
 * CONSTANTS
 * ======================================= */

define('OPT_SOURCE_FOLDER', 'sourcefolder');
define('OPT_PROCESSING_FOLDER', 'processingfolder');
define('OPT_CONFIG_FILE', 'configfile');
define('OPT_LANGUAGE', 'language');
define('OPT_LOGFILE', 'logfile');
define('OPT_FOREVER', 'forever');
define('OPT_LOGSTYLE', 'logstyle');

// Exit codes:
define('EXIT_OK', 0);
define('EXIT_ERROR_ARGS', 10);
define('EXIT_ERROR_INIT', 11);
define('EXIT_ERROR_RUNTIME', 12);   // Unknown error during runtime.
define('EXIT_ERROR_ITEMS', 20);     // There was a problem with >0 Items


/* ========================================
 * GLOBAL VARIABLES
 * ======================================= */

$logger;                            // Logging handler
$cinbox;                            // Inbox handler
$config;                            // Config handler



/* ========================================
 * FUNCTIONS
 * ======================================= */


/**
 * Parse the commandline arguments.
 */
function parseArgs()
{
    global $logger, $config, $argv;
    $l = $logger;

    // Available commandline arguments (see showHelp() for details):
    $shortOpts = "i:p:c:n:w:hv";
    $longOpts = array(
            'debug',                    // logLevel = DEBUG
            'logstyle:',                // Set output format of Item log
            'lang:',                    // Set language. Syntax is same as in config file: de_DE, en_GB, etc...
            'log:',                     // Set logfile for inbox (items will have their own)
            'forever',                  // Continue loop until cancelled by user
            'version',                  // Show version number
            );

    $options = getopt($shortOpts, $longOpts);
    if (empty($options) || isset($options['h'])) 
    {
        showHelp();
        return EXIT_OK;
    }

    // ------------------------------------------------
    // Evaluate and set options:

    if (isset($options['i']))
    {
        $config->set(OPT_SOURCE_FOLDER, $options['i']);
    }

    if (isset($options['p']))
    {
        $config->set(OPT_PROCESSING_FOLDER, $options['p']);
    }

    if (isset($options['c']))
    {
        $config->set(OPT_CONFIG_FILE, $options['c']);
    }

    if (isset($options['v']))
    {
        $l->setLogLevel(Logger::OUT_SCREEN, Logger::LEVEL_INFO);
    }

    //TODO: Implement all commandline options.

    if (isset($options['debug']))
    {
        $l->setLogLevel(Logger::OUT_SCREEN, Logger::LEVEL_DEBUG);
        $l->setLogLevel(Logger::OUT_TEXTFILE, Logger::LEVEL_DEBUG);
        $l->logDebug(print_r($options, true));
    }

    if (isset($options['logstyle']))
    {
        $logstyle = $options['logstyle'];
        try { Logger::validateOutputFormat($logstyle); }
        catch (Exception $e)
        {
            $l->logError($e->getMessage());
            return false;
        }

        $config->set(OPT_LOGSTYLE, $logstyle);
    }

    if (isset($options['lang']))
    {
        $config->set(OPT_LANGUAGE, $options['lang']);
        // Errors with languages are non-critical, so we don't check.
        setLanguage($config->get(OPT_LANGUAGE));
    }

    if (isset($options['log']))
    {
        $config->set(OPT_LOGFILE, $options['log']);
    }

    if (isset($options['forever']))
    {
        $config->set(OPT_FOREVER, true);
    }

    if (isset($options['version']))
    {
        showVersion();
        exit(EXIT_OK);
    }

    // ------------------------------------------------

    return true;
}


/**
 * Just show the version number.
 */
function showVersion()
{
    printf("CInbox v%s (released: %s)\n",
            CINBOX_VERSION,
            CINBOX_DATE
          );
}


/**
 * Show help dialogue with commandline syntax.
 */
function showHelp()
{
    showVersion();
    printf(_("
Usage: %s [OPTION]

Mandatory arguments:
  -i                         Inbox source folder

Optional arguments:
  -c                         Config file. If none given, default INI file in source folder is used
  -h                         Show help/syntax
  -p                         Processing folder (contains logs, etc)
  -v                         Verbose mode (logLevel = INFO)
  --debug                    Debug mode (logLevel = DEBUG)
  --logstyle STYLE           Set output format of Item log
                             STYLE can be: 'classic' or 'cv'
  --lang                     Set language. Syntax is same as in config file: de_DE, en_GB, etc.
  --log                      Set logfile for inbox (items will have their own)
  --forever                  Continue looping/waiting for items until interrupted by user
  --version                  Show version number

EXIT VALUES
  %d       Success
  %d      Syntax or usage error in arguments
  %d      Error during initialization
  %d      Runtime error
  %d      Errors occurred while processing an item

"),
        basename(__FILE__),
        EXIT_OK,
        EXIT_ERROR_ARGS,
        EXIT_ERROR_INIT,
        EXIT_ERROR_RUNTIME,
        EXIT_ERROR_ITEMS
    );

    /* TODO:
       Optional (overrides settings in config file):
       -n                         Items at once
       -w                         Cooloff/wait time (in minutes)
     */
}


function setLanguage($language)
{
    global $logger;
    $l = $logger;

    $l->logMsg(sprintf(_("Setting language to '%s'."), $language));
    $locale = setlocale(LC_MESSAGES, $language);

    if (empty($locale))
    {
        $l->logWarning(sprintf(_("Language '%s' not supported. Is matching locale installed?"), $language));
        return false;
    }

    putenv('LANGUAGE=' . $locale);
    $domain = 'messages';
    $pathToLocale = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'locale');

    if (empty($pathToLocale))
    {
        $l->logWarning(_("Language folder name empty."));
        return false;
    }

    if (!is_dir($pathToLocale))
    {
        $l->logWarning(sprintf(_("Cannot find language folder: '%s'."), $pathToLocale));
        return false;
    }

    $l->logDebug(sprintf(_("Bind textdomain '%s': %s"), $domain, bindtextdomain($domain, $pathToLocale)));
    $l->logDebug(sprintf(_("Bind textdomain codeset: %s"), bind_textdomain_codeset($domain, "UTF-8")));

    // Use the messages domain
    $l->logDebug(sprintf(_("Use textdomain: %s"), textdomain($domain)));

    return true;
}


/**
 *
 */
function initInbox()
{
    global $logger, $config, $cinbox;
    $l = $logger;

    $l->logMsg(_("Initializing Inbox..."));
    $l->logNewline();

    if (!function_exists("gettext"))
    {
        $l->logError("ERROR: PHP installation is missing 'gettext'.");
        exit(1);
    }

    // Create Inbox object instance:
    $cinbox = new \ArkThis\CInbox\CInbox($logger);
    $cinbox->setLogfile($config->get(OPT_LOGFILE));

    $cinbox->setSourceFolder($config->get(OPT_SOURCE_FOLDER));
    $cinbox->setConfigFile($config->get(OPT_CONFIG_FILE));
    $cinbox->setItemLogstyle($config->get(OPT_LOGSTYLE));
    $result = $cinbox->initInbox();

    return $result;
}



/* ========================================
 * INITIALIZATION
 * ======================================= */

// Initialize output/logging:
$logger = new \ArkThis\Logger();
$l = $logger;                           // Alias as shortcut.

$config = new \ArkThis\CInbox\CIConfig($logger);
$config->set(OPT_FOREVER, false);       // Don't continue forever by default.

// Parse commandline arguments:
if (!parseArgs()) exit(EXIT_ERROR_ARGS);


/* ========================================
 * MAIN
 * ======================================= */

try
{
    if (!initInbox()) throw new \Exception();
}
catch(Exception $e)
{
    $l->logException(_("Could not init Inbox due to errors:"), $e);
    exit(EXIT_ERROR_INIT);
}

try
{
    $itemErrors = $cinbox->run($config->get(OPT_FOREVER));
}
catch(Exception $e)
{
    $l->logException(_("Error during Inbox execution:"), $e);
    exit(EXIT_ERROR_RUNTIME);
}

if ($itemErrors > 0) exit(EXIT_ERROR_ITEMS);
exit(EXIT_OK);

?>
