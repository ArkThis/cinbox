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
    private $charMapping;
    protected $cleanSource;



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

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

        // Construct correct character mapping based on config:
        $this->setMapping($this->cleanSource);

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
            $clean = $this->cleanFilename(basename($filename));
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

                $l->logMsg(sprintf(_("Cleaning filename '%s' to '%s'."), $fileIn, $clean));
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

        $this->cleanSource = $config->get(self::CONF_CLEAN_SOURCE);
        // TODO: Set defaults if no config is given?
        if (!$this->optionIsArray($this->cleanSource, self::CONF_CLEAN_SOURCE)) return false;
        $l->logDebug(sprintf(_("Clean source: %s"), implode(', ', $this->cleanSource)));

        return true;
    }



    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Define custom mapping of characters for cleanFilename().
     */
    public function setMapping($mapping)
    {
        $charMapping = array();

        foreach ($mapping as $key)
        {
            $key = strtolower($key);
            $charMapping = array_merge($charMapping, $this->charMappings[$key]);
        }

        $this->charMapping = $charMapping;

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

        $charsIllegal = array_keys($this->charMapping);
        $charsReplace = array_values($this->charMapping);
        $cleanFilename = str_replace($charsIllegal, $charsReplace, $filename);

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



}

?>
