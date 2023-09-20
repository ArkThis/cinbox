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

use \ArkThis\CInbox\Task\CITask;
use \ArkThis\CInbox\Task\TaskFilesMatch;
use \Exception as Exception;

#require_once('tasks/CITask.php');
#require_once('tasks/TaskFilesMatch.php');


/**
 * Checks if all file/folder entries in this folder are present
 * which are configured as MUST_EXIST.
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
class TaskFilesMustExist extends TaskFilesMatch
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Must-exist filenames';

    /**
     * @name Task Settings
     * Names of config settings used by this task
     */
    //@{
    const CONF_MUST_EXIST = 'MUST_EXIST';               ///< Patterns of files that must exist in this folder.
    //@}



    /* ========================================
     * PROPERTIES
     * ======================================= */

    private $patternsMustExist;                         ///< @see #CONF_MUST_EXIST



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);
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

        // Test invalid files pattern:
        $missing = $this->hasMissingFiles($this->CIFolder, $this->patternsMustExist);
        if ($missing > 0)
        {
            $l->logError(sprintf(_("Folder '%s' is missing files/subfolders that must exist."), $this->CIFolder->getSubDir()));
            $this->setStatusPBCT();
            return false;
        }

        $this->setStatusDone();
        return true;
    }


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

        // TODO: Sanity check values here.

        $this->patternsMustExist = $config->get(self::CONF_MUST_EXIST);
        // Task is optional, therefore it's okay if setting is empty:
        if(empty($this->patternsMustExist)) return $this->skipIt();
        if (!$this->optionIsArray($this->patternsMustExist, self::CONF_MUST_EXIST)) return false;
        $l->logDebug(sprintf(
                    _("Patterns for must-exist files: %s"),
                    implode(', ', $this->patternsMustExist)
                    ));

        return true;
    }


    /**
     * @name Task-specific methods
     */
    //@{

    //@}



}

?>
