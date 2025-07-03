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
use \ArkThis\CInbox\CIExec;
use \ArkThis\Helper;
use \Exception as Exception;


/**
 * This task copies the files from source folder to target folder.
 *
 * The actual file transfer process is done by 'rsync' to ensure reliable data transfer.
 * @See TaskCopyRsync
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
class TaskCopyToTarget extends TaskCopyRsync
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Copy to target';



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
        $l = $this->logger;

        if (!parent::init()) return false;

        // Must return true on success:
        return true;
    }


    /**
     * Perform the actual steps of this task.
     */
    public function run()
    {
        if (!parent::run()) return false;

        // Copy files of this folder to targetFolderStage:
        if (!$this->copyFolder($this->sourceFolder, $this->targetFolder, $this->targetFolderStage)) return false;

        // Must return true on success:
        $this->setStatusDone();
        return true;
    }



    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    protected function copyFolder($sourceFolder, $targetFolder, $targetFolderStage)
    {
        $l = $this->logger;
        $errors = 0;
        $entryCount = 0;
        $copyCount = 0;

        // Verify if desired target folder is okay:
        if (!$this->checkTargetFolderCondition($targetFolder, $this->updateFolders)) return false;
        if (!$this->createFolder($targetFolderStage))
        {
            $this->setStatusPBCT();
            return false;
        }

        $list = Helper::getFolderListing2($sourceFolder);
        ksort($list); // sort filenames alphabetically

        // Iterate through listing of entries in current folder:
        foreach ($list as $key=>$entry)
        {
            // Only iterate through files. Each task only handles the current folder (CIFolder).
            if (is_dir($key)) continue;

            $entryCount++;
            $sourceFile = $key;
            $targetFile = Helper::getTargetFilename($sourceFile, $targetFolder);

            $targetFileTemp = Helper::getTargetFilename($sourceFile, $targetFolderStage);

            if (!$this->checkTargetFileCondition($targetFile, $this->updateFiles))
            {
                $errors++;
                continue;
            }

            // Skip file if it matches 'CONF_COPY_EXCLUDE' patterns:
            $exclude = $this->exclude($sourceFile, $this->copyExclude);
            if ($exclude !== false)
            {
                $l->logMsg(sprintf(_("Excluding file '%s'. Matches pattern '%s'."), $sourceFile, $exclude));
                continue;
            }

            $l->logMsg(sprintf(_("Copying '%s' to '%s'..."), $sourceFile, $targetFileTemp));
            $exitCode = $this->copyFile($sourceFile, $targetFileTemp);
            ($exitCode == CIExec::EC_OK) ? $copyCount++ : $errors++;
        }

        if ($entryCount == 0)
        {
            $l->logMsg(sprintf(_("No files to copy in '%s'. Fine."), $sourceFolder));
            return true;
        }

        $l->logMsg(sprintf(
                    _("%d/%d file(s) copied from '%s' to '%s'."),
                    $copyCount,
                    $entryCount,
                    $sourceFolder,
                    $targetFolder));

        if ($errors > 0)
        {
            $l->logError(sprintf(
                        _("%d errors encountered trying to copy '%s' to '%s'."),
                        $errors,
                        $sourceFolder,
                        $targetFolder));
            $this->setStatusPBCT();

            // Remove empty subfolders on targetFolderStage (recursive rmdir?).
            $l->logInfo(sprintf(_("Removing empty temp-folders in '%s'..."), $targetFolderStage));
            Helper::removeEmptySubfolders($targetFolderStage);
            return false;
        }

        return true;
    }



}

?>
