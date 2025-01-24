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
use \ArkThis\CInbox\Task\TaskExec;
use \Exception as Exception;


/**
 * Runs Post-processor scripts, in order as configured.
 * Requires *all* Post-proc scripts to run successfully to return positive status.
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
class TaskPostProcs extends TaskExec
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Run Post-Processors';

    // Names of config settings used by a task must be defined here.
    const CONF_POSTPROCS = 'POSTPROCS';                   // Post-processing scripts



    /* ========================================
     * PROPERTIES 
     * ======================================= */

    // Class properties are defined here.
    protected $postProcs;



    /* ========================================
     * METHODS 
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, static::TASK_LABEL);
    }



    /**
     * @name Common task functions
     */
    //@{

    /**
     * Load settings from config that are relevant for this task.
     */
    protected function loadSettings()
    {
        if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        $this->postProcs = $config->get(static::CONF_POSTPROCS);
        // Task is optional, therefore it's okay if setting is empty:
        if(empty($this->postProcs)) return $this->skipIt();
        if (!$this->optionIsArray($this->postProcs, static::CONF_POSTPROCS)) return false;
        $l->logDebug(sprintf(_("Post-processing scripts:\n%s"), print_r($this->postProcs, true)));

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
        $scripts = $this->postProcs;

        $l->logMsg(sprintf(_("Running %d post-processing scripts in folder '%s' ..."), count($scripts), $this->CIFolder->getSubDir()));

        // Execute scripts and handle output.
        foreach ($scripts as $index=>$script)
        {
            $l->logMsg(sprintf(
                        _("Executing post-processor #%d: '%s'"),
                        $index+1,
                        $script
                        ));

            $exitCode = $this->runScript($script);
            $this->setStatusByExitcode($exitCode);

            if ($exitCode > static::EXIT_STATUS_DONE)
            {
                $l->logMsg(sprintf(
                            _("Call returned with non-zero exit code: %d\nCommand was: '%s'"),
                            $exitCode,
                            $this->exec->getLastCommand()
                            ));
                return false;
            }
        }

        // Task-status should have been set by "setStatusByExitcode()" by now.
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
