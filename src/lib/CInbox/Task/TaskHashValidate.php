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
 * Validates hashcodes of the target files by comparing them to
 * the ones in temp-folder.
 * This requires the Task 'HashGenerate' to be run before this one.
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
class TaskHashValidate extends TaskHash
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Validate target hashcodes';



    /* ========================================
     * PROPERTIES 
     * ======================================= */

    // Class properties are defined here.



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
        // TODO: Optional. Initialize things here before running the task.

        // Check if we have a proper temp folder:
        if (!$this->checkTempFolder()) return false;

        // Must return true on success:
        return true;
    }


    /**
     * Perform the actual steps of this task.
     *
     * IMPORTANT:
     *   This task must be run *before* TaskRenameTarget, because if validation fails,
     *   after renaming, the Item cannot easily be reset, without deleting the target-leftovers...
     *   Therefore we use "targetFolderTemp".
     *
     * @see $this->resolveTargetFolderTemp()
     */
    public function run()
    {
        if (!parent::run()) return false;

        if (!$this->checkTarget($this->sourceFolder, $this->targetFolderTemp)) return false;

        // Must return true on success:
        $this->setStatusDone();
        return true;
    }


    /**
     * Actions to be performed *after* run() finished successfully;
     */
    public function finalize()
    {
        if (!parent::finalize()) return false;

        // TODO: Optional. Initialize things here before running the task.
        // TODO: Add here what the task is supposed to do.

        // Must return true on success:
        return true;
    }



    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    protected function checkTarget($sourceFolder, $targetFolder)
    {
        $l = $this->logger;

        $l->logNewline();
        $l->logMsg(sprintf(_("Checking target folder '%s'..."), $targetFolder));

        $entryCount = 0;
        $errors = 0;
        $hashType = $this->hashType;

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

            $exclude = $this->exclude($sourceFile, $this->copyExclude);
            if ($exclude !== false)
            {
                $l->logMsg(sprintf(_("Excluding file '%s'. Matches pattern '%s'."), $sourceFile, $exclude));
                continue;
            }

            $hashCode = $this->getTempHashForFilename($sourceFile);
            $l->logInfo(sprintf(
                _("Validating target '%s' against source hash (%s): %s"),
                basename($targetFile),
                $hashType,
                $hashCode));

            $hashCodeTarget = $this->generateHashcode($this->hashType, $targetFile);
            if (empty($hashCodeTarget))
            {
                $l->logError(sprintf(_("hash_file failed: Could not create hash (%s) for '%s'."), $hashType, $targetFile));
                $errors++;
                $this->setStatusPBCT();
                continue;
            }

            if (!$this->compareHashcode($hashCode, $hashCodeTarget))
            {
                $l->logError(sprintf(_("Hashcode mismatch for '%s': Is %s but should be %s."), $targetFile, $hashCodeTarget, $hashCode));
                $errors++;
                $this->setStatusPBCT();
                continue;
            }

            $l->logMsg(sprintf(_("Target '%s' is valid."), $targetFile));
        }

        if ($errors > 0) return false;
        return true;
    }

}

?>
