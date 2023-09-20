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


/**
 * Skeleton to use when creating a new task.
 * Search for the word "TODO" and follow the instructions.
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
// TODO: Change the class name to match your case:
class TaskSkeleton extends CITask
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // TODO: Change the taskname here:
    //       (this is not a description, just a label!)
    const TASK_LABEL = 'Task Skeleton';                     ///< Human readable task name/label.

    /**
     * @name Task Settings
     * Names of config settings used by this task
     */
    //@{
    // TODO: Names of config settings used by a task must be defined here.
    //       Config names shall start with "CONF_" (see examples below).
    //       Each config entry must be documented inline with "///<".
    //
    //       Examples:
    //          const CONF_HASH_TYPE = 'HASH_TYPE';         ///< Define which hash type
    //          const CONF_MUST_EXIST = 'MUST_EXIST';       ///< Stuff that must exist
    //@}



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
     * @name Common task functions
     */
    //@{

    /**
     * Load settings from config that are relevant for this task.
     *
     * @retval boolean
     *  True if everything went fine, False if an error occurred.
     */
    protected function loadSettings()
    {
        if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        // TODO: load settings from $config.

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
        // TODO: Add here what the task is supposed to do.

        // Must return true on success:
        $this->setStatusDone();
        return true;
    }


    /**
     * Actions to be performed *after* run() finished successfully;
     *
     * @retval boolean
     *  True if everything went fine, False if an error occurred.
     */
    public function finalize()
    {
        if (!parent::finalize()) return false;

        // TODO: Optional. Do things here that need to be done *after*
        //       the actual task has finished.
        //       For example clean-up things or so.

        // Must return true on success:
        return true;
    }

    //@}


    /**
     * @name Task-specific methods
     */
    //@{

    // TODO: Add your methods here.
    // Default type is 'protected'. Use 'public' functions only where necessary.

    //@}

}

?>
