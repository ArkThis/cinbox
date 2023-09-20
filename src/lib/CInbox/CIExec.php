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

namespace ArkThis\CInbox;

/**
 * Provides a simple handler for executing external commands.
 * Supports returning the exit code and text output of the externally called command.
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
class CIExec
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    const EC_OK = 0;                            // Exitcode for "ok"


    /* ========================================
     * PROPERTIES
     * ======================================= */

    protected $exitCode = null;                 // Exit code of process
    protected $lastCmd = null;                  // Last command executed
    protected $lastOutput = null;               // Output of last command



    /* ========================================
     * METHODS
     * ======================================= */


    /**
     * Returns the exit code of the last command executed.
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }


    /**
     * Return the output of the last command (as array).
     */
    public function getLastOutput()
    {
        return $this->lastOutput;
    }


    /**
     * Returns the commandline string that was last executed.
     */
    public function getLastCommand()
    {
        return $this->lastCmd;
    }


    /**
     * This is a quick-n-dirty solution for calling an external command,
     * but without wanting to handle its output/feedback/etc.
     *
     * It returns the exit code of the $command:
     * Usually 0 is good - and non-zero means error.
     */
    public function executeBlindly($command)
    {
        $this->exitCode = null;     // Reset to avoid leftovers.
        $this->lastCmd = $command;

        passthru($command, $this->exitCode);

        return $this->exitCode;
    }


    /**
     * This uses "exec()" to call $command.
     */
    public function execute2($command)
    {
        $this->exitCode = null;     // Reset to avoid leftovers.
        $this->lastCmd = $command;

        exec($command, $this->lastOutput, $this->exitCode);

        return $this->exitCode;
    }


    /**
     * Wrapper to default-execution method.
     */
    public function execute($command)
    {
        return $this->executeBlindly($command);
    }



}

?>
