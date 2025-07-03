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
 * Abstract base class for hashcode-related tasks.
 * Provides common methods useful for handling hashcodes 
 * as well as read/writing their temp files.
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
abstract class TaskHash extends CITask
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Hash Base (abstract)';

    // Names of config settings used by a task must be defined here.
    const CONF_HASH_TYPE = 'HASH_TYPE';                         // Which hashcode algorithm to use. See php documentation on 'hash_file()' which ones are supported.



    /* ========================================
     * PROPERTIES 
     * ======================================= */

    // Class properties are defined here.
    private $hashTypesAllowed;
    protected $hashType;



    /* ========================================
     * METHODS 
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

        // Allow all hash algorithms supported by PHP version:
        $this->hashTypesAllowed = hash_algos();
    }


    /**
     * Load settings from config that are relevant for this task.
     */
    protected function loadSettings()
    {
        if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        // Load hash algorithm type:
        $hashType = strtolower($config->get(self::CONF_HASH_TYPE));
        $l->logDebug(sprintf(_("Hashcode type (algorithm): %s"), $hashType));

        // Check if provided hashcode algorithm type is supported:
        if (!$this->hashTypeIsAllowed($hashType)) return false;
        $this->hashType = $hashType;


        // Must return true on success:
        return true;
    }



    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Writes the hascode to a file.
     *
     * Hashcode is forced to lowercase.
     * Default format: Plain text. Just the hashcode and a newline (\n) character. Nothing else.
     */
    protected function saveHashToFile($fileName, $hashCode)
    {
        if (!Helper::touchFile($fileName)) return false;

        $hashCode = strtolower($hashCode);
        $result = file_put_contents($fileName, $hashCode . "\n");

        return $result;
    }


    /**
    * Reads the hashcode from a file.
    * The format of the file must match the format that "saveHashToFile" writes.
    * Returns the hashcode in lowercase.
    */
    public function loadHashFromFile($fileName)
    {
        $hashCode = null;

        if (!file_exists($fileName))
        {
            throw new Exception(sprintf(_("loadHashFromFile: Hashcode file '%s' does not exist."), $fileName));
        }

        $contents = file_get_contents($fileName);

        // Remove linebreaks and other non-printable characters before/after hashcode:
        $hashCode = strtolower(trim(str_replace(array("\r\n", "\r"), "\n", $contents)));

        return $hashCode;
    }


    /**
    * Returns the hashcode from pre-calculated temp hashfiles.
    * Requires TaskHashGenerate to be run before.
    */
    public function getTempHashForFilename($fileName)
    {
        $hashFile = $this->getHashTempFilename($fileName, $this->CIFolder, $this->hashType);

        return $this->loadHashFromFile($hashFile);
    }


    /**
     * Returns an array with hashcodes of all files in the given folder.
     * Hashcodes are read from temp-hashfiles.
     * Array structure is:
     *   key:   filename (absolute)
     *   value: hashcode
     */
    protected function getTempHashForFolder($folderName)
    {
        $l = $this->logger;

        $hashCodes = array();
        $entryCount = 0;
        $errors = 0;

        $list = Helper::getFolderListing2($folderName);
        ksort($list); // sort filenames alphabetically

        foreach ($list as $key=>$entry)
        {
            // Don't process subfolders. Only files.
            if (is_dir($key)) continue;

            $entryCount++;
            $sourceFile = $key;

            $hashCode = $this->getTempHashForFilename($sourceFile);
            if (empty($hashCode))
            {
                $l->logError(sprintf(_("Could not get temp-hash (%s) for '%s'."), $hashType, $sourceFile));
                $errors++;
                continue;
            }

            $hashCodes[$sourceFile] = $hashCode;
        }

        if ($errors > 0) return false;
        return $hashCodes;
    }


    /**
     * Returns the filename where the hashcode is temporarily stored for $fileName.
     * This method is static, so it can be used by other tasks to determine where to find the temporary hashcode files.
     */
    public static function getHashTempFilename($fileName, $CIFolder, $hashType)
    {
        // TODO: Replace this by $this->getTempFolder()?
        $tempFolder = $CIFolder->getTempFolder();
        $baseFolder = $CIFolder->getBaseFolder();

        // Recreate the same subfolder structure in temp-folder (by replacing baseFolder substring with tempFolder),
        // and then adding the hash-type (algo) string as file suffix:
        $hashFile = str_replace($baseFolder, $tempFolder, $fileName) . '.' . strtolower($hashType);

        return $hashFile;
    }


    /**
     * Deletes the file where the hashcode is temporarily stored for $fileName.
     * This method is static, so it can be used by other tasks to remove the temporary hashcode files.
     */
    public static function removeHashTempFile($fileName, $CIFolder, $hashType)
    {
        $hashFile = static::getHashTempFilename($fileName, $CIFolder, $hashType);

        // File doesn't exist anymore? Fine!
        if (!file_exists($hashFile)) return true;

        if (!unlink($hashFile))
        {
            throw new Exception(sprintf(_("Could not delete temp hashfile '%s'"), $hashFile));
        }
        return true;
    }


    /**
     * Returns an array containing the hash algorithm types allowed/supported.
     */
    public function getHashTypesAllowed()
    {
        return $this->hashTypesAllowed;
    }


    /**
    * Checks if provided hashtype is supported by this task.
    * By default, all hash algorithms supported by PHP are allowed.
    */
    protected function hashTypeIsAllowed($hashType)
    {
        $hashType = strtolower($hashType);

        if (!in_array($hashType, $this->hashTypesAllowed))
        {
            throw new Exception(sprintf(_("Hash type '%s' is invalid or not supported by this PHP version.\nValid types are: %s"), $hashType, implode(' ', $this->hashTypesAllowed)));
        }

        return true;
    }


    /**
     * Generates a hashcode for a file.
     */
    protected function generateHashcode($hashType, $fileName)
    {
        $l = $this->logger;

        if (!$this->hashTypeIsAllowed($hashType)) return false;

        // TODO: Check hash_file with >2GB files.
        $hashCode = hash_file($hashType, $fileName);
        if (empty($hashCode)) return false;

        return $hashCode;
    }


    /**
     * Checks if $hash1 matches $hash2.
     * Comparison is case insensitive.
     */
    protected function compareHashcode($hash1, $hash2)
    {
        $result = strcasecmp($hash1, $hash2);
        if ($result == 0) return true;
        return false;
    }


}

?>
