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
use \ArkThis\Helper;
use \Exception as Exception;


/**
 * For files matching 'HASH_MUST_EXIST', this task searches for existing hashes in files
 * matching 'HASH_SEARCH' in the source folder in order to compare the current hashcode against it.
 *
 * This allows to validate already existing hashes to document the chain of file transfer.
 * It must be run *after* TaskHashGenerate, because the hashcodes are read from the temp-files
 * created in that task.
 *
 *
 * @author Peter Bubestinger-Steindl (pb@av-rd.com)
 * @copyright
 *  Copyright 2018 AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.av-rd.com/products/cinbox/">CInbox product website</a>
 *  - <a href="http://www.av-rd.com/">AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
class TaskHashSearch extends TaskHash
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Hashcode search';

    // Names of config settings used by a task must be defined here.
    const CONF_HASH_SEARCH = 'HASH_SEARCH';             // Glob-pattern where to find hashcodes
    const CONF_HASH_MUST_EXIST = 'HASH_MUST_EXIST';     // Glob-pattern for which files hash must be present



    /* ========================================
     * PROPERTIES
     * ======================================= */

    // Class properties are defined here.
    protected $hashSearch;
    protected $hashMustExist;



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);
    }



    /**
     * Prepare everything so it's ready for processing.
     * @return bool     success     True if init went fine, False if an error occurred.
     */
    public function init()
    {
        if (!parent::init()) return false;

        // Check if we have a proper temp folder:
        if (!$this->checkTempFolder()) return false;

        // Must return true on success:
        return true;
    }


    /**
     * Perform the actual steps of this task.
     */
    public function run()
    {
        if (!parent::run()) return false;

        $l = $this->logger;

        $l->logMsg(sprintf(_("Searching existing hashcodes for files in '%s'..."), $this->sourceFolder));
        $filesMatched = $this->searchHashCodes($this->sourceFolder);

        if (!$this->checkMustExist($filesMatched)) return false;

/*
        TODO: Remove certain hashcode files after positive match?
        Tricky is to define which ones are okay to delete?
        Example:
        XMLs possibly contain information to keep, but
        .md5 files probably not.
        Maybe handle this as optional post-processor?
*/

        // Must return true on success:
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

        $this->hashSearch = $config->get(self::CONF_HASH_SEARCH);
        // This check is optional, so setting can be empty.
        if(!empty($this->hashSearch))
        {
            if (!$this->optionIsArray($this->hashSearch, self::CONF_HASH_SEARCH)) return false;
            $l->logDebug(sprintf(
                        _("Hashcode search patterns: %s"),
                        implode(', ', $this->hashSearch)
                        ));
        }

        $this->hashMustExist = $config->get(self::CONF_HASH_MUST_EXIST);
        // This check is optional, so setting can be empty.
        if(!empty($this->hashMustExist))
        {
            if (!$this->optionIsArray($this->hashMustExist, self::CONF_HASH_MUST_EXIST)) return false;
            $l->logDebug(sprintf(
                        _("Hashcodes must exist for: %s"),
                        implode(', ', $this->hashMustExist)
                        ));
        }

        // TODO/FIXME:
        // If hashSearch is empty, but hashMustExist is set: STATUS_CONFIG_ERROR

        // Must return true on success:
        return true;
    }



    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Iterates through all files in source folder and searches for existing checksums.
     */
    protected function searchHashCodes($sourceFolder)
    {
        $l = $this->logger;
        $hashType = $this->hashType;

        if (empty($this->hashType))
        {
            // If no hashing algorithm/type is set, we can't find anything. No good.
            throw new Exception(sprintf(_("findHashes: hashType (%s) is not configured."), self::CONF_HASH_TYPE));
        }

        // Remove trailing slash, if existing:
        $sourceFolder = rtrim($sourceFolder, DIRECTORY_SEPARATOR);

        $l->logInfo(sprintf(_("searchHashCode: Hashtype is '%s'."), $hashType));

        // get a list of all files in folder:
        $all = glob($sourceFolder . DIRECTORY_SEPARATOR . '*');
        sort($all);

        $filesMatched = array();
        foreach ($all as $fileName)
        {
            if (is_file($fileName))
            {
                $filenameRelative = Helper::getAsRelativePath($fileName, $this->CIFolder->getBaseFolder());

                $hashCode = $this->getTempHashForFilename($fileName);
                if (empty($hashCode))
                {
                    // TODO: decide how to handle this situation. Is it critical? Should this be just a warning message?
                    $l->logError(sprintf(_("No temp hash existing for '%s'. This is odd. Task 'TaskHashGenerate' must have been run before this one."), $fileName));
                    $this->setStatusPBCT();
                    continue;
                }

                $matches = $this->searchHashCode($sourceFolder, $hashCode);
                if (!empty($matches))
                {
                    $l->logMsg(sprintf(_("%d files containing hash code match for '%s' found (%s = %s)"), count($matches), $filenameRelative, $hashType, $hashCode));
                    $filesMatched[] = $fileName;
                }
            }
        }

        return $filesMatched;
    }


    /**
     * Tries to find the given hashcode in the source folder matching the glob patterns
     * in "$this->hashSearch glob".
     * Returns an array with key is filename where match was found - and value is an array of lines containing the hashcode.
     * Returns false if no match is found.
     */
    protected function searchHashCode($sourceFolder, $hashCode)
    {
        $l = $this->logger;
        $hashSearch = $this->hashSearch;

        if (empty($this->hashSearch))
        {
            // Search mask is probably not configured, so nothing to search/find.
            return false;
        }

        $l->logInfo(sprintf(_("Searching hashcode '%s' in '%s'..."), $hashCode, implode(', ', $hashSearch)));

        $hashFoundFiles = null;

        // Create an array of the list of files which might contain hashcodes:
        $hashSources = array();
        foreach ($hashSearch as $pattern)
        {
            $matching = glob($sourceFolder . DIRECTORY_SEPARATOR . $pattern);
            sort($matching);
            $hashSources = array_merge($hashSources, $matching);
        }

        $l->logDebug(sprintf(_("Files matching search patterns:\n%s"), print_r($hashSources, true)));

        // Check for hash collisions/duplicates is done in TaskHashGenerate - not here.
        foreach ($hashSources as $fileName)
        {
            if (is_file($fileName))
            {
                $linesMatch = $this->findHashInFile($fileName, $hashCode);
                if (!empty($linesMatch) && is_array($linesMatch))
                {
                    $hashFoundFiles[$fileName] = $linesMatch;
                    $l->logInfo(sprintf(_("Lines containing hash match in '%s':\n%s"), $fileName, print_r($linesMatch, true)));
                }
            }
        }

        return $hashFoundFiles;;
    }


    /**
     * Searches the given hashcode in a file.
     *
     * @return  Array of String
     * @retval  linesMatch  Array of the matching lines (if any were found).
     * @retval  NULL        If contents of hash-containing file ($fileName) were empty.
     * @retval  FALSE       If no lines matching the hashcode were found.
     */
    protected function findHashInFile($fileName, $hashCode)
    {
        if (!file_exists($fileName) || !is_readable($fileName))
        {
            throw new Exception(sprintf(_("File does not exist or is not readable: '%s'"), $fileName));
        }

        // We don't force upper/lower case here to allow case-sensitivity in the future (if needed).
        $hashCode = trim($hashCode);
        if (empty($hashCode))
        {
            throw new Exception(_("Empty hashcode given."));
        }

        $lines = file($fileName);

        if ($lines === false)
        {
            throw new Exception(sprintf(_("Could not read file '%s'."), $fileName));
        }

        // If file to search hashcode in is empty, there's no need to proceed:
        if (empty($lines)) return null;

        // Find lines matching hash:
        $lineNo = 1;
        $linesMatch = array();
        foreach ($lines as $line)
        {
            // Found matching hash(es).
            if (strpos($line, $hashCode) !== false)
            {
                // Save matching hashSource filename, line number and line string:
                $linesMatch[$lineNo] = rtrim(str_replace(array("\r\n", "\r"), "\n", $line), "\n");
                $lineNo++;
            }
        }

        // No matching lines:
        if (count($linesMatch) == 0) return false;

        return $linesMatch;
    }


    /**
     * Checks if all files in "$filesMatched" have a matching hashcode.
     */
    protected function checkMustExist($filesMatched)
    {
        $l = $this->logger;
        $sourceFolder = $this->sourceFolder;
        $hashMustExist = $this->hashMustExist;

        if (empty($hashMustExist))
        {
            // No must-exists.
            $l->logMsg(_("No must exists set. Fine."));
            return true;
        }

        $missing = 0;
        $counter = 0;
        foreach ($hashMustExist as $pattern)
        {
            $mustExist = glob($sourceFolder . DIRECTORY_SEPARATOR . $pattern);
            if (empty($mustExist))
            {
                // Only existing files need a matching hashcode. So this is not an error:
                $l->logInfo(sprintf(_("No files matching '%s = %s'. That's okay."), self::CONF_HASH_MUST_EXIST, $pattern));
                continue;
            }

            $doExist = array_intersect($filesMatched, $mustExist);
            $diff = array_diff($mustExist, $doExist);

            if (!empty($diff))
            {
                $l->logError(sprintf(_("Existing hashcode missing for %d files (%s):\n%s"), count($diff), $pattern, print_r($diff, true)));
                $this->setStatusPBCT();
                $missing += count($diff);

                // Remove temp-hash for files in $diff, in order to have the hash
                // recalculated once the affected files have been replaced/fixed
                // and the Item reset from Error to To-Do.
                foreach ($diff as $filename)
                {
                    $l->logMsg(sprintf(_("File is probably corrupt: '%s'. Removing its temp hashfile..."), $filename));
                    $this->removeHashTempFile($filename, $this->CIFolder, $this->hashType);
                }
            }

            $counter += count($mustExist);
        }

        if ($missing == 0)
        {
            // Everything found as desired.
            $l->logMsg(sprintf(_("Existing hashcodes found for all %d files matching: %s"), $counter, implode(', ', $hashMustExist)));
            return true;
        }

        return false;
    }



}

?>
