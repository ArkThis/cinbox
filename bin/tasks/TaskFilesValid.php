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

require_once('tasks/CITask.php');
require_once('tasks/TaskFilesMatch.php');


/**
 * Checks if entries in this folder (files/subfolders) are valid or invalid,
 * according to configuration options: CONF_FILES_VALID / CONF_FILES_INVALID
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
class TaskFilesValid extends TaskFilesMatch
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Valid filetypes';

    // Names of config settings used by a task:
    const CONF_FILES_VALID = 'FILES_VALID';
    const CONF_FILES_INVALID = 'FILES_INVALID';



    /* ========================================
     * PROPERTIES
     * ======================================= */

    private $patternsValid;
    private $patternsInvalid;



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);
    }


    /**
     * Perform the actual steps of this task.
     *   - Check for invalid files matching 'FILES_INVALID'
     *   - check for non-valid files not-matching 'FILES_VALID'
     */
    public function run()
    {
        if (!parent::run()) return false;

        $l = $this->logger;

        // Test invalid files pattern:
        if ($this->hasInvalidFiles($this->CIFolder, $this->patternsInvalid)) return false;

        // Test non-valid files pattern:
        if ($this->hasNonValidFiles($this->CIFolder, $this->patternsValid)) return false;

        $this->setStatusDone();
        return true;
    }


    protected function loadSettings()
    {
        if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        // TODO: Sanity check values here.

        $this->patternsValid = $config->get(self::CONF_FILES_VALID);
        // This check is optional, so setting can be empty.
        if(!empty($this->patternsValid))
        {
            if (!$this->optionIsArray($this->patternsValid, self::CONF_FILES_VALID)) return false;
            $l->logDebug(sprintf(
                        _("Patterns for valid files: %s"),
                        implode(', ', $this->patternsValid)
                        ));
        }

        $this->patternsInvalid = $config->get(self::CONF_FILES_INVALID);
        // This check is optional, so setting can be empty.
        if (!empty($this->patternsInvalid))
        {
            if (!$this->optionIsArray($this->patternsInvalid, self::CONF_FILES_INVALID)) return false;
            $l->logDebug(sprintf(
                        _("Patterns for invalid files: %s"),
                        implode(', ', $this->patternsInvalid)
                        ));
        }

        if (empty($this->patternsValid) && empty($this->patternsInvalid))
        {
            // Nothing to check:
            $this->skipIt();
        }

        return true;
    }


    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Returns "true" if folder contains files matching patterns in 'FILES_INVALID'.
     * False if not.
     */
    protected function hasInvalidFiles($CIFolder, $patterns)
    {
        $l = $this->logger;

        if (empty($patterns))
        {
            // No pattern = no filter. No invalid files. This is NOT an error:
            $l->logInfo(_("No filter for invalid files set."));
            return false;
        }

        $files = $this->getMatchingFiles($CIFolder, $patterns);
        if (!empty($files))
        {
            $l->logError(sprintf(
                        _("%d invalid file(s) found in '%s':\n%s"),
                        count($files),
                        $CIFolder->getSubDir(),
                        print_r($files, true)
                        ));
            $this->setStatusError();
            return true;
        }

        $l->logMsg(sprintf(
                    _("Folder '%s' contains no invalid files/subfolders (Matching '%s'). Good."),
                    $CIFolder->getSubDir(),
                    implode(', ', $patterns)
                    ));
        return false;
    }


    /**
     * Returns "true" if folder contains files that do NOT match the patterns in 'FILES_VALID'.
     * False if not.
     */
    protected function hasNonValidFiles($CIFolder, $patterns)
    {
        $l = $this->logger;

        if (empty($patterns))
        {
            // No pattern = no filter. No non-valid files. This is NOT an error:
            $l->logInfo(_("No filter for valid files set."));
            return false;
        }

        // apply "is_file" filter to exclude folder entries:
        $files = array_filter($this->getNonMatchingFiles($CIFolder, $patterns), 'is_file');
        if (!empty($files))
        {
            $l->logError(sprintf(
                        _("%d non-valid file(s) found in '%s':\n%s"),
                        count($files),
                        $CIFolder->getSubDir(),
                        print_r($files, true)
                        ));
            $this->setStatusError();
            return true;
        }

        $l->logMsg(sprintf(
                    _("Folder '%s' contains only valid files (Matching '%s'). Good."),
                    $CIFolder->getSubDir(),
                    implode(', ', $patterns)
                    ));
        return false;
    }

}

?>
