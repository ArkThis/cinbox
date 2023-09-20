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

#require_once('include/CIFolder.php');
#require_once('include/Helper.php');


/**
 * Renames the target from temporary to final.
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
class TaskRenameTarget extends TaskCopy
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Rename target to final';

    // Task specifics:
    const IS_RECURSIVE = true;



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
     * Perform the actual steps of this task.
     */
    public function run()
    {
        if (!parent::run()) return false;

        $l = $this->logger;

        foreach ($this->itemSubDirs as $subDir=>$CIFolder)
        {
            $targetFolder = $CIFolder->getTargetFolder();
            $targetFolderTemp = $this->resolveTargetFolderTemp($CIFolder);

            // Log the resolved target folder in a machine readable way:
            $CIFolder->logTargetFolder($hasOwn=true, $isAbsolute=true);

            if (!file_exists($targetFolderTemp))
            {
                $l->logWarning(sprintf(_("Temp folder does not exist anymore: '%s'"), $targetFolderTemp));
                $this->setStatusPBC();
                continue;
            }

            if (!file_exists($targetFolder) && !$this->createFolder($targetFolder))
            {
                $this->setStatusPBCT();
                return false;
            }

            // Target folder now exists: Merge files from temp into it:
            if (!$this->mergeFiles($targetFolderTemp, $targetFolder))
            {
                $this->setStatusPBCT();
                return false;
            }
        }

        // Must return true on success:
        $this->setStatusDone();
        return true;
    }


    public function finalize()
    {
        if (!parent::finalize()) return false;

        // Temp folders should be empty now. Remove them:
        foreach ($this->itemSubDirs as $subDir=>$CIFolder)
        {
            $targetFolderTemp = $this->resolveTargetFolderTemp($CIFolder);
            Helper::removeEmptySubfolders($targetFolderTemp);
        }
    }


    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Integrates files (not folders!) from $sourceFolder into $targetFolder.
     * Files in source will overwrite files in target folder.
     */
    protected function mergeFiles($sourceFolder, $targetFolder)
    {
        $l = $this->logger;
        $entryCount = 0;
        $errors = 0;

        $l->logMsg(sprintf(_("Merging files from '%s' into '%s'..."), $sourceFolder, $targetFolder));

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

            if (!$this->move($sourceFile, $targetFile)) $errors++;
        }

        if ($errors > 0)
        {
            $l->logError(sprintf(_("%d of %d files had errors."), $errors, $entryCount));
            return false;
        }

        return true;
    }



}

?>
