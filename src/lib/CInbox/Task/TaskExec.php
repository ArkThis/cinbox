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
 * Abstract base class for tasks that call external applications.
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
abstract class TaskExec extends CITask
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Execute external programs (abstract)';

    /**
     * @name Exit codes
     *
     * Exit-codes of external scripts that represent task states.
     * Task states #STATUS_RUNNING and #STATUS_UNDEFINED are internal only
     * and therefore not available to external program execution.
     *
     * NOTE: These numbers are not mapped 1:1 to task states, because
     *       application exit codes are defined as:
     *          0 = okay
     *          non-0 = error
     *       Yet, the same numbers were used wherever possible.
     *
     * IMPORTANT:
     * All programs/scripts executed by this task must implement these
     * exit codes. Otherwise, a non-0 exitcode might lead to undesired,
     * possibly catastrophic results.
     * If you need to execute an arbitrary program, please use a
     * wrapper-script of some kind to return these exit codes as desired.
     */
    //@{
    const EXIT_STATUS_DONE = 0;                  ///< Success! This means the task completed successfully.
    const EXIT_STATUS_WAIT = 5;                  ///< Task decided that Item is not ready yet and shall be moved back to 'to-do'.
    const EXIT_STATUS_PBCT = 6;                  ///< There were problems, but the task may continue.
    const EXIT_STATUS_PBC = 7;                   ///< There were problems, but subsequent task may continue.
    const EXIT_STATUS_ERROR = 10;                ///< An error occurred. Abort execution as soon as possible.
    const EXIT_STATUS_CONFIG_ERROR = 11;         ///< If a config option was not valid
    const EXIT_STATUS_SKIPPED = 15;              ///< If task was skipped
    //@}


    /* ========================================
     * PROPERTIES
     * ======================================= */

    protected $exec;

    /**
     * $logFile: Contains filename for output of external command execution.
     * But should only be used if exactly 1 command is called once per class
     * instantiation. Otherwise, please resolve logfile name using getCmdLogfile()
     * right before executing the external application.
     */
    protected $logFile;



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

        // Initialize handler for executing external commands:
        $this->exec = new CIExec();
    }



    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Takes a commandline string consisting of program and arguments
     * and checks if the program file exists and is executable.
     */
    protected function isCmdValid($command)
    {
        if (empty($command))
        {
            throw new \Exception(_("Commandline empty."));
        }

        // NOTE: This might go wrong with spaces in arguments or program name!
        $pieces = explode(" ", $command);

        $program = $pieces[0];      // Program to call should be the string before first space

        if (!file_exists($program))
        {
            throw new \Exception(sprintf(_("Program does not exist: '%s'"), $program));
        }

        if (!is_executable($program))
        {
            throw new \Exception(sprintf(_("Program is not executable: '%s'"), $program));
        }

        // Must return true on success:
        return true;
    }


    /**
     * Resolves placeholders in command string.
     */
    protected function resolveCmd($command, $arguments=null)
    {
        $config = $this->config;
        return $config->resolveString($command, $arguments);
    }


    /**
    * Takes the name of a shell-script (or any other command), and executes it
    * after resolving placeholders e.g. as arguments.
    */
    protected function runScript($script)
    {
        $l = $this->logger;

        // Bail out on first sign of error:
        if (!$this->isCmdValid($script)) return false;
        $command = $this->resolveCmd($script);

        $l->logMsg($l->getHeadline());
        $l->logInfo(sprintf(_("Executing command:\n'%s'"), $command));
        $exitCode = $this->exec->execute($command);
        $l->logNewline();

        return $exitCode;
    }


    /**
     * Sets the task status according to the given exitcode.
     * This can be used to have external scripts determine
     * tasklist flow.
     */
    protected function setStatusByExitcode($exitCode)
    {
        switch ($exitCode)
        {
            case self::EXIT_STATUS_DONE:
                $this->setStatusDone();
                break;

            case self::EXIT_STATUS_WAIT:
                $this->setStatusWait();
                break;

            case self::EXIT_STATUS_PBCT:
                $this->setStatusPBCT();
                break;

            case self::EXIT_STATUS_PBC:
                $this->setStatusPBC();
                break;

            case self::EXIT_STATUS_ERROR:
                $this->setStatusError();
                break;

            case self::EXIT_STATUS_CONFIG_ERROR:
                $this->setStatusConfigError();
                break;

            case self::EXIT_STATUS_SKIPPED:
                $this->setStatusSkipped();
                break;
        }
    }


    /**
     * Get filename of logfile to use for external command execution.
     * Filename is "$prefix + DATEIME" (TIME precision: seconds).
     * If $prefix is empty, the task name will be used.
     *
     * NOTE: Assumes $this->tempFolder to be set and initialized.
     *
     * @param $prefix   [string]    String to prefix logfile name with.
     *
     * @See: self::checkTempFolder()
     */
    protected function getCmdLogfile($prefix=null)
    {
        // Check if we have a proper temp folder:
        if (!$this->checkTempFolder()) return false;

        if (empty($prefix)) $prefix = $this->name;

        $logFile = Helper::resolveString(
                $this->tempFolder . DIRECTORY_SEPARATOR . $prefix . __DATETIME__ . '.log',
                array(__DATETIME__ => date('Ymd_His'))
                );

        return $logFile;
    }


    /**
     * Removes/deletes the logfile used for external command execution.
     * @See self::getCmdLogfile()
     */
    protected function removeCmdLogfile($logFile=null)
    {
        $l = $this->logger;

        // If no filename is given, use the task's property:
        if (empty($logFile)) $logFile = $this->logFile;

        if (empty($logFile))
        {
            //This shouldn't happen, but well...
            $l->logError(sprintf(
                        _("Logfile for task '%s' cannot be deleted, since no filename has been set. This is odd, but not critical."),
                        $this->name
                        ));
            $this->setStatusPBC();
            return false;
        }

        if (!file_exists($logFile))
        {
            // Logfile isn't there, so remove can report "success" ;)
            $l->logDebug(sprintf(_("Logfile '%s' removed, since it didn't exist in the first place ;)"), $logFile));
            return true;
        }

        if (!is_writable($logFile))
        {
            $l->logError(sprintf(_("Logfile '%s' cannot be deleted, because it is not writable. Check access rights?"), $logFile));
            // Mark this "problem but continue", since it is non-critical to this or following tasks:
            $this->setStatusPBC();
            return false;
        }

        if (!unlink($logFile))
        {
            $l->logError(sprintf(_("Logfile '%s' could not be deleted. Unknown reason."), $logFile));
            // Mark this "problem but continue", since it is non-critical to this or following tasks:
            $this->setStatusPBC();
            return false;
        }

        $l->logDebug(sprintf(_("Logfile '%s' was deleted."), $logFile));
        return true;
    }


    /**
     * Used to write a message to the command execution logfile.
     * Appends if file already exists, otherwise creates it before.
     */
    protected function writeToCmdLogfile($msg, $logFile=null)
    {
        $l = $this->logger;

        // Nothing to do. Nothing done. Okay.
        if (empty($msg)) return true;

        // If no filename is given, use the task's property:
        if (empty($logFile)) $logFile = $this->logFile;

        if (!file_exists($logFile)) touch($logFile);

        $result = file_put_contents($logFile, $msg, FILE_APPEND);
        if ($result > 0)
        {
            $l->logDebug(sprintf(_("Wrote message to logfile '%s' (%d bytes):\n%s"), $logFile, $result, $msg));
        }
        else
        {
            $l->logError(sprintf(_("Error writing message '%s' to logfile '%s'."), $msg, $logFile));
            return false;
        }

        return $result;
    }



}

?>
