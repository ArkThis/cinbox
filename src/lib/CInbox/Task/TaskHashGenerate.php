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
use \ArkThis\CInbox\Task\TaskHash;
use \Exception as Exception;


/**
 * Generates hashcodes for files in folder.
 *
 *
 * @author Peter Bubestinger-Steindl (pb@av-rd.com)
 * @copyright
 *  Copyright 2023 ArkThis AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.av-rd.com/products/cinbox/">CInbox product website</a>
 *  - <a href="http://www.av-rd.com/">AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
class TaskHashGenerate extends TaskHash
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Generate Hashcodes';



    /* ========================================
     * PROPERTIES
     * ======================================= */



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

        // Generate and save hashcodes:
        if (!$this->generateHashcodes($this->sourceFolder)) return false;

        $hashDuplicates = $this->checkForHashDuplicates();
        if (!empty($hashDuplicates))
        {
            $l->logError(sprintf(_("Hash duplicates found:\n%s"), print_r($hashDuplicates, true)));
            $this->setStatusPBCT();
            return false;
        }

        // Must return true on success:
        $this->setStatusDone();
        return true;
    }



    /**
     * @name Task-specific methods
     */
    //@{


    /**
     * Generates hashcodes for all files in $sourceFolder and saves them to temp-files.
     *
     * For each file, it checks if the temp-file can be written, before calculating the actual hashcode.
     * This avoids unnecessary hashcode calculation in case that the result could not be written anyway.
     */
    protected function generateHashcodes($sourceFolder)
    {
        $l = $this->logger;
        $hashType = $this->hashType;
        $folder = $this->CIFolder;
        $hashCodes = array();           // This will contain the hashcodes for each file. key=filename, value=hashcode.

        $l->logMsg(sprintf(_("Generating hashcodes for files in '%s'..."), $sourceFolder));


        $errors_calc = 0;               // Errors during calculating hashcodes
        $errors_write = 0;              // Errors during writing hashcodes to file
        $cached = 0;                    // Number of hashes read from tempfiles (=cache).

        $list = Helper::getFolderListing2($sourceFolder);
        ksort($list); // sort filenames alphabetically

        foreach ($list as $key=>$entry)
        {
            if (is_file($key))
            {
                $fileName = $key;
                $filenameRelative = Helper::getAsRelativePath($fileName, $folder->getBaseFolder());

                $hashFile = $this->getHashTempFilename($fileName, $folder, $hashType);
                $l->logDebug(sprintf(_("Hash file for '%s': %s"), $fileName, $hashFile));

                // Check if we can write temp hashfile *before* calculating hash. Saves time in case of problems.
                // But *only* write hash if the file does not exist yet.
                if (!file_exists($hashFile) && !Helper::touchFile($hashFile))
                {
                    $l->logError(sprintf(_("Hashcode file '%s' does not exist, and could not be created. Check access rights? Disk space?"), $hashFile));
                    $l->logError(sprintf(_("Skipping hash for '%s'"), $filenameRelative));
                    $this->setStatusPBCT();
                    $errors_write++;
                    continue;
                }

                // This operation is time- and CPU-consuming. Should only be executed if result can be stored:
                $hashCode = $this->generateHashcode(null, $fileName);
                if ($hashCode === false)
                {
                    $this->setStatusPBCT();
                    $errors_calc++;
                    continue;
                }
                if ($hashCode === true)
                {
                    // Hash tempfile already existing.
                    $cached++;
                    continue;
                }
                $hashCodes[$fileName] = $hashCode;

                $l->logMsg(sprintf(_("Writing hash file '%s'..."), $hashFile));
                if ($this->saveHashToFile($hashFile, $hashCode))
                {
                    $l->logDebug(sprintf(_("Wrote hash file '%s'."), $hashFile));
                }
                else
                {
                    $l->logError(sprintf(_("Could not write hashcode to file '%s'. Check access rights? Disk space?"), $fileName));
                    $this->setStatusPBCT();
                    $errors_write++;
                    continue;
                }
            }
        }
        // Makes the order in which files are written more nice for humans:
        ksort($hashCodes);

        $this->hashCodes = $hashCodes;

        $counter = count($hashCodes);
        if ($counter > 0)
        {
            $l->logMsg(sprintf(_("Hashcode for %d file(s) generated."), $counter));
        }
        if ($cached > 0)
        {
            $l->logMsg(sprintf(_("Hashcode for %d file(s) read from cache in temp folder."), $cached));
        }

        // Report on errors occurred:
        if ($errors_calc > 0)
        {
            $l->logError(sprintf(_("%d problems encountered during hashcode calculation (%s)."), $errors_calc, $hashType));
        }

        if ($errors_write > 0)
        {
            $l->logError(sprintf(_("%d problems encountered while saving hashcodes to file."), $errors_write));
        }

        if ($errors_calc + $errors_write > 0)
        {
            $this->setStatus(self::STATUS_ERROR);
            return false;
        }

        return true;
    }


    /**
     * Generates a hashcode for a file.
     *
     * The hashcode algorithm type is defined in '$this->hashType' and therefore
     * ignored, but the function declaration must match:
     * TaskHash::generateHashcode($hashType, $fileName).
     */
    protected function generateHashcode($hashType=null, $fileName)
    {
        $l = $this->logger;
        $folder = $this->CIFolder;
        $hashType = $this->hashType;
        $filenameRelative = Helper::getAsRelativePath($fileName, $folder->getBaseFolder());

        $hashFile = $this->getHashTempFilename($fileName, $folder, $hashType);

        $l->logDebug(sprintf(_("Hash file for '%s': %s"), $fileName, $hashFile));       // TODO: Change to logDebug
        // hashFile was touched before, so filesize can be greater than 0 - but smaller than with actual hashcode content.
        // IDEA: It might be possible to use 'sizeof(hash($hashType, "---"))' as limit for valid filesize for this hashType.
        if (file_exists($hashFile) && filesize($hashFile) > 1)
        {
            // Continue to next file if hash-file for $filename already exists:
            // Saving computing cycles saves time, produces less heat and therefore saves climate - and nature! :)
            $l->logInfo(sprintf(_("Skipping file. Hashfile already exists (%s)."), $hashFile));
            return true;
        }

        $l->logInfo(sprintf(_("Hashing '%s'..."), $filenameRelative));

        $hashCode = TaskHash::generateHashcode($hashType, $fileName);
        if (empty($hashCode))
        {
            $l->logError(sprintf(_("hash_file failed: Could not create hash (%s) for '%s'."), $hashType, $fileName));
            $this->setStatusPBCT();
            return false;
        }

        $l->logInfo(sprintf(_("Hashcode (%s) for '%s' = %s"), $hashType, $filenameRelative, $hashCode));

        return $hashCode;
    }


    /**
     * Checks if each hashcode only appears exactly once.
     * If files in source have identical hashcodes, this either means there are duplicates
     * or danger of possible hashcode collision.
     *
     * Returns array of duplicate hashcodes (if found).
     * Returns false if all hashcodes are unique.
     */
    protected function checkForHashDuplicates()
    {
        $hashCodes = $this->hashCodes;
        
        $hashCodesUnique = array_unique($hashCodes);
        $diff = array_diff($hashCodes, $hashCodesUnique);

        if (empty($diff))
        {
            // No duplicates:
            return false;
        }

        // Possible duplicates:
        return $diff;
    }

    //@}



}

?>
