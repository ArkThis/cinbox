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
 * This abstract task is used as base for tasks using Rsync as copy tool.
 *
 * The actual file transfer process is done by 'rsync' to ensure reliable data transfer.
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
 *  - <a href="http://rsync.samba.org/">rsync web page</a>
 *  - <a href="http://www.ArkThis.com/">ArkThis AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
abstract class TaskCopyRsync extends TaskCopy
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Copy files using RSync (abstract)';

    const BIN_RSYNC = 'rsync';                          ///< This command is called for performing the actual copy.
    const CMD_COPY_MASK = 'rsync --progress --times --copy-links --inplace --log-file="[@LOGFILE@]" "[@FILE_IN@]" "[@FILE_OUT@]"';
    const CMD_COPY_MASK_DEBUG = 'rsync --dry-run -v --progress --times --copy-links --inplace --log-file="[@LOGFILE@]" "[@FILE_IN@]" "[@FILE_OUT@]"';



    /* ========================================
     * PROPERTIES
     * ======================================= */



    /* ========================================
     * METHODS
     * ======================================= */

    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------


    /**
     * Copies a single file from A to B.
     * Uses external command to do this.
     *
     * @see self::CMD_COPY_MASK
     */
    protected function copyFile($sourceFile, $targetFile)
    {
        $l = $this->logger;

        $tempFolder = $this->tempFolder;

        $arguments = array(
                __FILE_IN__ => $sourceFile,
                __FILE_OUT__ => $targetFile,
                __LOGFILE__ => $this->logFile,
                );

        $command = Helper::resolveString(self::CMD_COPY_MASK, $arguments);
        $l->logDebug(sprintf(_("Copy command: %s"), $command));

        $exitCode = $this->exec->execute($command);

        if ($exitCode != CIExec::EC_OK)
        {
            $l->logError(sprintf(
                        _("Copy command returned exit code '%d': %s\nCommandline: %s"),
                        $exitCode,
                        $this->getRsyncErrorMessage($exitCode),
                        $command));
        }

        return $exitCode;
    }


    /**
     * Returns a human readable error message for a given rsync exit code value.
     * If $exitcode=null, it returns an array with all messages (and exit code as key).
     *
     * Error messages are based on documentation of "exit values" found in:
     *    http://www.samba.org/ftp/rsync/rsync.html
     */
    public static function getRsyncErrorMessage($exitCode=null)
    {
        $errorMessages = array(
                0 => 'Success',
                1 => 'Syntax or usage error',
                2 => 'Protocol incompatibility',
                3 => 'Errors selecting input/output files, dirs',
                4 => 'Requested action not supported: an attempt was made to manipulate 64-bit files on a platform that cannot support them, or an option was specified that is supported by the client and not by the server.',
                5 => 'Error starting client-server protocol',
                6 => 'Daemon unable to append to log-file',
                10 => 'Error in socket I/O',
                11 => 'Error in file I/O',
                12 => 'Error in rsync protocol data stream',
                13 => 'Errors with program diagnostics',
                14 => 'Error in IPC code',
                20 => 'Received SIGUSR1 or SIGINT',
                21 => 'Some error returned by waitpid()',
                22 => 'Error allocating core memory buffers',
                23 => 'Partial transfer due to error',
                24 => 'Partial transfer due to vanished source files',
                25 => 'The --max-delete limit stopped deletions',
                30 => 'Timeout in data send/receive',
                35 => 'Timeout waiting for daemon connection',
                127 => 'Command not found: ' . self::BIN_RSYNC,
                );

        if (is_null($exitCode))
        {
            // Return the whole error message collection:
            return $errorMessages;
        }

        if (array_key_exists($exitCode, $errorMessages))
        {
            return $errorMessages[$exitCode];
        }

        return sprintf(_("Invalid or unknown exitcode value: '%d'"), $exitCode);
    }



}

?>
