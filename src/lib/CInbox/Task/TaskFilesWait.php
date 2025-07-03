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
 * Checks if certain file/folder entries in this folder are present
 * which are configured as FILES_WAIT.
 *
 * This is handy in cases where an Item is incomplete for a longer period
 * of time, and considered ready for processing until certain files
 * are present.
 *
 * @see TaskFilesMustExist
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
class TaskFilesWait extends TaskFilesMatch
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Wait for files';

    /**
     * @name Task Settings
     * Names of config settings used by this task
     */
    //@{
    const CONF_FILES_WAIT = 'FILES_WAIT';                   ///< Patterns of files to wait for.
    //@}



    /* ========================================
     * PROPERTIES
     * ======================================= */

    // Class properties are defined here.
    private $patternsFilesWait = null;                      ///< @see #CONF_FILES_WAIT



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);
    }


    /**
     * @name Common task functions
     */
    //@{

    /**
     * Load settings from config that are relevant for this task.
     *
     * @retval boolean
     *  True if everything went fine. False if not.
     */
    protected function loadSettings()
    {
        if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        $this->patternsFilesWait = $config->get(self::CONF_FILES_WAIT);
        // Task is optional, therefore it's okay if setting is empty:
        if(empty($this->patternsFilesWait)) return $this->skipIt();
        if (!$this->optionIsArray($this->patternsFilesWait, self::CONF_FILES_WAIT)) return false;
        $l->logDebug(sprintf(
                    _("Patterns for files to wait for: %s"),
                    implode(', ', $this->patternsFilesWait)
                    ));

        // Must return true on success:
        return true;
    }


    /**
     * Prepare everything so it's ready for processing.
     *
     * @retval boolean
     *  True if task shall proceed. False if not.
     */
    public function init()
    {
        if (!parent::init()) return false;
        // TODO: Optional. Initialize things here before running the task.

        // Must return true on success:
        return true;
    }


    /**
     * Perform the actual steps of this task.
     *
     * @retval boolean
     *  True if task shall proceed. False if not.
     */
    public function run()
    {
        if (!parent::run()) return false;

        $l = $this->logger;

        $missing = $this->hasMissingFiles($this->CIFolder, $this->patternsFilesWait);
        if ($missing > 0)
        {
            $l->logMsg(sprintf(
                        _("Folder '%s' is missing %d files/subfolders matching '%s' that must exist before we can proceed."),
                        $this->CIFolder->getSubDir(),
                        $missing,
                        implode(', ', $this->patternsFilesWait)
                        ));
            $this->setStatusWait();
            return false;
        }

        // Must return true on success:
        $this->setStatusDone();
        return true;
    }

    //@}


    /**
     * @name Task-specific methods
     */
    //@{

    //@}

}

?>
