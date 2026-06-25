<?php
/*
   This file is part of 'CInbox' (Common-Inbox)

   'CInbox' is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   'CInbox' is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with 'CInbox'.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace ArkThis\CInbox\Task;

use \ArkThis\CInbox\CIFolder;
use \Exception as Exception;
use \RuntimeException as RuntimeException;
use \UConverter as UConverter;


/**
 * Cleans files/foldernames of the folder, applying certain replacement rules for 'non-safe' characters.
 *
 *
 * @author Peter Bubestinger-Steindl (cinbox (at) ArkThis.com)
 * @copyright
 *  Copyright 2025 ArkThis AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.ArkThis.com/products/cinbox/">CInbox product website</a>
 *  - <a href="https://github.com/ArkThis/cinbox/">CInbox source code</a>
 *  - <a href="http://www.ArkThis.com/">ArkThis AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
class TaskCleanFilenames extends TaskFilesMatch
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Clean filenames';

    // Names of config settings used by a task must be defined here.
    const CONF_CLEAN_SOURCE = 'CLEAN_SOURCE';
    const CONF_CLEAN_CUSTOM = 'CLEAN_CUSTOM';
    const CONF_CLEAN_CONVERT = 'CLEAN_CONVERT';

    // Mapping of characters to escape them in a meaningful way:
    // Umlauts:
    public static $CHARS_UMLAUTS = array(
            "ä" => "ae",
            "ü" => "ue",
            "ö" => "oe",
            "ß" => "ss",

            "Ä" => "AE",
            "Ü" => "UE",
            "Ö" => "OE",
            );

    // Illegal:
    public static $CHARS_ILLEGAL = array(
            "?" => "_",
            "*" => "_",
            ":" => "_",
            );

    // TODO: Can TAB be in a filename?
    //       other non-printable chars here?
    // Spaces...
    public static $CHARS_WHITESPACE = array(
            " " => "_",
            );

    public static $CHARS_SLASHES = array(
            "\\" => "_",
            "/" => "_",
            );

    // Brackets
    public static $CHARS_BRACKETS = array(
            "(" => "_",
            ")" => "_",
            "[" => "_",
            "]" => "_",
            "{" => "_",
            "}" => "_",
            "<" => "_",
            ">" => "_",
            );

    // Quotation marks and ticks
    // Replace them with "less critical" ones.
    public static $CHARS_QUOTATION = array(
            "„" => "\"",
            "“" => "\"",
            "´" => "'",
            "`" => "'",
            );

    // Quotation marks and ticks
    // Remove them completely.
    public static $CHARS_QUOTATION2 = array(
            "„" => "_",
            "“" => "_",
            "´" => "_",
            "`" => "_",
            '"' => "_",
            "'" => "_",
            );

    // Even more optional (being picky!):
    public static $CHARS_PICKY = array(
            "#" => "_",
            "," => "_",
            ";" => "_",
            "&" => "and",
            );



    /* ========================================
     * PROPERTIES
     * ======================================= */

    private $charMappings;
    private $charMappingsCustom;
    private $charMapping;
    protected $cleanSource;
    protected $cleanCustom;
    protected $cleanConvert;



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

        // By default, we don't expect or use external mappings:
        $this->charMappingsCustom = null;

        // Define the default lists:
        $this->charMappings = array(
                'illegal' => self::$CHARS_ILLEGAL,
                'whitespace' => self::$CHARS_WHITESPACE,
                'slashes' => self::$CHARS_SLASHES,
                'umlauts' => self::$CHARS_UMLAUTS,
                'brackets' => self::$CHARS_BRACKETS,
                'quotation' => self::$CHARS_QUOTATION,
                'quotation2' => self::$CHARS_QUOTATION2,
                'picky' => self::$CHARS_PICKY,
                );

        // Only replace illegal characters by default:
        $this->setMapping(array('illegal'));
    }



    /**
     * Prepare everything so it's ready for processing.
     * @return bool     success     True if init went fine, False if an error occurred.
     */
    public function init()
    {
        if (!parent::init()) return false;

        // Load custom mappings from external files:
        // (This must come /before/ setMapping, so $charMappings contains the
        // new custom options:
        if (!empty($this->cleanCustom))
        {
            $this->loadCustom();
        }

        // Construct correct character mapping based on config:
        if (!empty($this->cleanSource))
        {
            $this->setMapping($this->cleanSource);
        }

        return true;
    }


    /**
     * Perform the actual steps of this task.
     */
    public function run()
    {
        if (!parent::run()) return false;

        $l = $this->logger;

        // Generate a file listing and sort it alphabetically:
        $all = $this->getMatchingFiles($this->CIFolder, array('*'));
        sort($all);

        $count = 0;
        $error = 0;
        foreach ($all as $filename)
        {
            // TODO: only clean filename *IF* it contains non alphanumeric characters (or spaces).

            $base = basename($filename);
            $clean = $this->cleanFilename($base);
            $fileIn = $filename;
            $fileOut = dirname($filename) . DIRECTORY_SEPARATOR . $clean;

            // Show renaming that will occur:
            if (strcmp($fileIn, $fileOut) != 0)
            {
                $count++;
                if (!$this->renameFile($fileIn, $fileOut))
                {
                    $error++;
                }

                $l->logInfo(sprintf(_("Cleaning filename '%s' to '%s'."), $base, $clean));
                $l->logDebug(sprintf(_("  - In:  '%s'"), $fileIn));
                $l->logDebug(sprintf(_("  - Out: '%s'"), $fileOut));
            }
        }

        if ($count > 0)
        {
            $l->logMsg(sprintf(_("Cleaned %d names."), $count));
        }

        if ($error > 0)
        {
            $l->logError(sprintf(_("Could not clean %d names."), $error));
            $this->setStatusPBCT();
            return false;
        }

        $this->setStatusDone();
        return true;
    }


    /**
     * Load settings from config that are relevant for this task.
     */
    protected function loadSettings()
    {
        if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        // ---------------------------
        $setting = $config->get(self::CONF_CLEAN_SOURCE);
        if(!empty($setting))
        {
            // TODO: Set defaults if no config is given?
            if (!$this->optionIsArray($setting, self::CONF_CLEAN_SOURCE)) return false;
            $l->logDebug(sprintf(_("Clean source: %s"), implode(', ', $setting)));
            $this->cleanSource = $setting;
        }

        // ---------------------------
        $setting = $config->get(self::CONF_CLEAN_CUSTOM);
        // This is optional, therefore it's okay if setting is empty:
        if(!empty($setting))
        {
            // TODO: Only load mappings from subfolder of cinbox (eg plugins) for "better" security?
            if (!$this->optionIsArray($setting, self::CONF_CLEAN_CUSTOM)) return false;
            $l->logDebug(sprintf(_("External files to load for custom mappings: %s"), implode(', ', $setting)));
            $this->cleanCustom = $setting;
        }

        // ---------------------------
        $setting = $config->get(self::CONF_CLEAN_CONVERT);
        // This is optional, therefore it's okay if setting is empty:
        if(!empty($setting))
        {
            // TODO: Only load mappings from subfolder of cinbox (eg plugins) for "better" security?
            $l->logDebug(sprintf(_("Enabled encoding-conversion to: %s"), $setting));
            $this->cleanConvert = strtoupper($setting); # Force UPPERCASE
        }

        return true;
    }



    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Assigns char-replacement mappings by key in $charMappings.
     */
    public function setMapping($mapping)
    {
        // Starts empty:
        $charMapping = array();
        // Load object-wide list locally for easier handling:
        $charMappings = $this->charMappings;

        foreach ($mapping as $key)
        {
            $key = strtolower($key);

            // Handle and catch if $key refers to an invalid list:
            if (!array_key_exists($key, $charMappings))
            {
                throw new RuntimeException(sprintf(
                    _("Invalid char mapping key '%s'! Valid options are: %s"),
                    $key,
                    implode(', ', array_keys($charMappings))
                ));
            }

            $charMapping = array_merge($charMapping, $charMappings[$key]);
        }

        $this->charMapping = $charMapping;

        return true;
    }


    /**
     * Adds character-mappings from $mappings to $this->charMappings.
     * The keys declared in $mappings will then be valid options in
     * CONF_CLEAN_SOURCE.
     */
    public function addMapping($mappings)
    {
        $l = $this->logger;

        $keys = array_keys($mappings);
        $l->logMsg(sprintf(
            _("Adding %d new mappings as option for %s: %s"),
            count($keys),
            self::CONF_CLEAN_SOURCE,
            implode(', ', $keys)
        ));

        $charMappings = array_merge($mappings, $this->charMappings);

        $this->charMappings = $charMappings;

        return true;
    }


    /**
     * Replaces possibly dangerous/problematic characters in $filename string.
     * To be used for file- or foldernames.
     */
    public function cleanFilename($filename)
    {
        $charMapping = $this->charMapping;

        if (empty($charMapping) || !is_array($charMapping))
        {
            throw new Exception(_("Empty or invalid character map."));
        }

        // Detect original encoding:
        $fromEncoding = mb_detect_encoding($filename, 'auto');

        $charsIllegal = array_keys($this->charMapping);
        $charsReplace = array_values($this->charMapping);
        $cleanFilename = str_replace($charsIllegal, $charsReplace, $filename);

        $cleanFilename = $this->convertEncoding(
            $cleanFilename,
            $toEncoding = $this->cleanConvert,
            $fromEncoding       # Assumed to be UTF-8 usually (in 2026)
        );

        return $cleanFilename;
    }


    /**
     * Rename a file from $fileIn to $fileOut.
     */
    protected function renameFile($fileIn, $fileOut)
    {
        $l = $this->logger;
        $l->logDebug(sprintf(_("Renaming '%s' to '%s'..."), $fileIn, $fileOut));

        if (!rename($fileIn, $fileOut))
        {
            throw new Exception(sprintf(_("Could not rename '%s' to '%s'."), $fileIn, $fileOut));
        }

        return true;
    }


    /**
     * Includes an external PHP code snippet, expecting the variable '$charMappingsCustom' to be defined.
     * The syntax there should be:
     *
     * `$charMappingsCustom['custom1'] = array('in' => 'out', ...)`
     */
    public function loadCustom()
    {
        $l = $this->logger;

        // This is initialized here, but overwritten once a custom file has been included below:
        $charMappingsCustom = null;

        // This contains a list of files to include:
        $cleanCustom = $this->cleanCustom;
        if (empty($cleanCustom))
        {
            $l->logDebug(_("No custom mappings defined."));
            return false;
        }
        // NOTE: loadSettings() should already check /if/ cleanCustom is an array or not!

        foreach ($cleanCustom as $file)
        {
            if (!file_exists($file))
            {
                throw new RuntimeException(sprintf(
                    _("Custom mapping file not found: '%s'"),
                    $file
                ));
            }

            $l->logMsg(sprintf(
                _("Loading custom mapping from file '%s'..."),
                $file
            ));

            // Actually load (=include the file) if it exists:
            // NOTE: Beware that this includes /and potentially runs/ code from $file!
            //       (so make sure it's within CInbox' plugins or binary
            //       folder, and keep it clean and safe)
            $included = include($file);
            // ---------------------------------------------

            // We expect the variable "$charMappingsCustom" to be populated in the included $file:
            if (empty($charMappingsCustom) OR !is_array($charMappingsCustom))
            {
                throw new Exception(sprintf(
                    _("Invalid custom mapping: '%s' variable is empty or not an array set in '%s'!"),
                    '$charMappingsCustom[]',
                    $file
                ));
            }

            $l->logInfo(sprintf(
                _("Loaded custom mappings from '%s':\n%s\n"),
                $file,
                print_r($included, true)
                ));

            // TODO: add mapping to use it
            $this->addMapping($charMappingsCustom);
        }
    }


    public function convertEncoding($string, $toEncoding, $fromEncoding='UTF-8')
    {
        $l = $this->logger;

        // Only convert /IF/ from and to encoding differ:
        if (strcmp($fromEncoding, $toEncoding) == 0)
        {
            $l->logDebug(sprintf(
                _("From/To encodings (%s/%s) match: skipping conversion. 😎️ (%s)"),
                $fromEncoding,
                $toEncoding,
                $string
            ));

            return $string;
        }

        // Change the encoding of a string:
        $options = array(
            'to_subst' => '_'
        );

        $l->logInfo(sprintf(
            _("Converting encoding from '%s' to '%s': %s"),
            $fromEncoding,
            $toEncoding,
            $string
        ));

		// Convert to limited charset, to replace unwanted characters:
        $converted = UConverter::transcode(
            $string,
            $toEncoding,
            $fromEncoding,
            $options
        );

		// Convert 'back' to wider charset (for sanity and compatibility):
		// (swapped from/to encoding arguments)
        $converted2 = UConverter::transcode(
            $converted,
            $fromEncoding,
            $toEncoding,
            $options
        );

        return $converted2;
    }

}

?>
