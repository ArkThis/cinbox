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
 * Abstract class containing functions/properties common to
 * Tasks that deal with copying/moving/renaming files from A to B.
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
//FIXME: Why is this not derived from TaskExec?
abstract class TaskCopy extends CITask
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Copy files (abstract)';

    // Names of config settings used by a task must be defined here.
    const CONF_UPDATE_FOLDERS = 'UPDATE_FOLDERS';           // Defines how to treat existing/new folder handling
    const CONF_UPDATE_FILES = 'UPDATE_FILES';               // Similar to UPDATE_FOLDERS, but for files

    // Valid options for settings.
    // Folder/file target update options:
    const OPT_CREATE = 'create';
    const OPT_UPDATE = 'update';
    const OPT_CREATE_OR_UPDATE = 'create_or_update';



    /* ========================================
     * PROPERTIES
     * ======================================= */

    public static $OPT_UPDATE_FOLDERS  = array(
            self::OPT_CREATE,               // Create target folders. ERROR if target already exists.
            self::OPT_UPDATE,               // Target folder must already exist, but contents can be changed.
            );

    public static $OPT_UPDATE_FILES = array(
            self::OPT_CREATE,               // Only allow creating NEW files. ERROR if file already exists on target.
            self::OPT_UPDATE,               // Target files MUST already exist, but its contents will be overwritten by source.
            self::OPT_CREATE_OR_UPDATE,     // Target files CAN exist, but don't have to. Files are either created or overwritten.
            );

    # Variables that contain the settings from config file:
    protected $updateFolders;
    protected $updateFiles;



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

        // This is used for calling external copy-tool later:
        $this->exec = new CIExec();
    }



    /**
     * Prepare everything so it's ready for processing.
     * @return bool     success     True if init went fine, False if an error occurred.
     */
    public function init()
    {
        $l = $this->logger;

        if (!parent::init()) return false;

        // Name of logfile containing feedback from the external copy-command:
        $this->logFile = $this->getCmdLogfile();

        // Must return true on success:
        return true;
    }


    /**
     * Actions to be performed *after* run() finished successfully;
     */
    public function finalize()
    {
        if (!parent::finalize()) return false;

        // Remove logfile (if successful/no errors occurred):
        $this->removeCmdLogfile();

        // Must return true on success:
        return true;
    }


    /**
     * Load settings from config that are relevant for this task.
     */
    protected function loadSettings()
    {
        if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        // ----------------------------
        $targetFolder = $config->get(CIFolder::CONF_TARGET_FOLDER);
        $targetStage = $config->get(CIFolder::CONF_TARGET_STAGE);

        // If a target-folder is given, then a target-stage MUST be set, too:
        if (!empty($targetFolder) && empty($targetStage))
        {
            $l->logError(sprintf(
                        _("No '%s' set for '%s'. Cannot continue."),
                        CIFolder::CONF_TARGET_STAGE,
                        CIFolder::CONF_TARGET_FOLDER,
                        ));
            $this->setStatusConfigError();
            return false;
        }

        // ----------------------------
        $this->updateFolders = strtolower($config->get(self::CONF_UPDATE_FOLDERS));         // Normalize to lowercase.
        $l->logDebug(sprintf(_("Copy update mode (folders): %s"), $this->updateFolders));
        if (!$this->isValidUpdateFolders($this->updateFolders))
        {
            $l->logError(sprintf(
                        _("Invalid value set for '%s': %s"),
                        self::CONF_UPDATE_FOLDERS,
                        $this->updateFolders));
            $this->setStatusConfigError();
            return false;
        }

        // ----------------------------
        $this->updateFiles = strtolower($config->get(self::CONF_UPDATE_FILES));             // Normalize to lowercase.
        $l->logDebug(sprintf(_("Copy update mode (files): %s"), $this->updateFiles));
        if (!$this->isValidUpdateFiles($this->updateFiles))
        {
            $l->logError(sprintf(_("Invalid value set for '%s': %s"),
                        self::CONF_UPDATE_FILES,
                        $this->updateFiles));
            $this->setStatusConfigError();
            return false;
        }

        // Must return true on success:
        return true;
    }



    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Checks if the given update mode is a valid option.
     * Returns 'true' if it is valid - 'false' if not.
     *
     * The check is case-sensitive, so you might want to normalize case before calling this.
     */
    protected function isValidUpdateFolders($updateMode)
    {
        return in_array($updateMode, static::$OPT_UPDATE_FOLDERS);
    }


    /**
     * Same as "isValidUpdateFolders()" but for file mode.
     */
    protected function isValidUpdateFiles($updateMode)
    {
        return in_array($updateMode, static::$OPT_UPDATE_FILES);
    }


    /**
     * Get filename of logfile to use for external command execution.
     * NOTE: Assumes $this->tempFolder to be set and initialized.
     *
     * @See: self::checkTempFolder()
     */
    // TODO: Move this to TaskExec!
    protected function getCmdLogfile()
    {
        // Check if we have a proper temp folder:
        if (!$this->checkTempFolder()) return false;

        $logFile = Helper::resolveString(
                $this->tempFolder . DIRECTORY_SEPARATOR . $this->name . __DATETIME__ . '.log',
                array(__DATETIME__ => date('Ymd_His'))
                );

        return $logFile;
    }


    /**
     * Removes/deletes the logfile used for external command execution.
     * @See self::getCmdLogfile()
     */
    // TODO: Move this to TaskExec!
    protected function removeCmdLogfile()
    {
        $l = $this->logger;
        $logfile = $this->logFile;

        if (!file_exists($logfile))
        {
            // Logfile isn't there, so remove can report "success" ;)
            $l->logDebug(sprintf(_("Logfile '%s' removed, since it didn't exist in the first place ;)"), $logfile));
            return true;
        }

        if (!is_writable($logfile))
        {
            $l->logError(sprintf(_("Logfile '%s' cannot be deleted, because it is not writable. Check access rights?"), $logfile));
            // Mark this "problem but continue", since it is non-critical to this or following tasks:
            $this->setStatusPBC();
            return false;
        }

        if (!unlink($logfile))
        {
            $l->logError(sprintf(_("Logfile '%s' could not be deleted. Unknown reason."), $logfile));
            // Mark this "problem but continue", since it is non-critical to this or following tasks:
            $this->setStatusPBC();
            return false;
        }

        $l->logDebug(sprintf(_("Logfile '%s' was deleted."), $logfile));
        return true;
    }


    /**
     * Creates a folder.
     * Performs checks before doing so and writes to log.
     * Returns 'true' if folder was created, 'false' if not.
     */
    protected function createFolder($folderName)
    {
        $l = $this->logger;

        if (empty($folderName))
        {
            // This is not an exception, so that task execution *may* still proceed in case of this error:
            // TODO: Throw custom exception. Seems cleaner.
            $l->logError(_("createFolder: Empty folder name. This should not happen."));
            $this->setStatusError();
            return false;
        }

        $l->logMsg(sprintf(_("Creating folder '%s'..."), $folderName));
        if (is_dir($folderName)) return true;

        # TODO: Create parent folders, too. CAUTION: Inherit permissions from existing parent folders.
        if (!mkdir($folderName))
        {
            $l->logErrorPhp(sprintf(_("Failed to create folder '%s'."), $folderName));
            return false;
        }

        return true;
    }


    /**
     * Renames/moves a file or folder from $source to $target.
     *
     * It uses the PHP built-in function "rename", but different checks:
     * $overwrite:
     *   If false: If $target already exists, exception will be thrown.
     *   If true:  If $target already exists it will be overwritten by $source.
     */
    protected function move($source, $target, $overwrite=false)
    {
        $l = $this->logger;

        if (empty($source)) throw new Exception(_("move: Empty source given. This should not happen."));
        if (empty($target)) throw new Exception(_("move: Empty target given. This should not happen."));
        if (!file_exists($source)) throw new Exception(sprintf(_("Source does not exist: '%s'."), $source));

        if (file_exists($target))
        {
            if (!$overwrite) throw new Exception(sprintf(_("Target already exists: '%s'."), $target));
            // Overwrite = 1. delete old target / 2. move new file into position (=replacing target).
            if (!unlink($target))
            {
                $l->logErrorPhp(sprintf(_("Unable to overwrite '%s'."), $target));
                $this->setStatusPBCT();
                return false;
            }
        }

        $l->logMsg(sprintf(_("Moving '%s' to '%s'..."), $source, $target));

        if (!rename($source, $target))
        {
            $l->logErrorPhp(sprintf(_("Failed to rename '%s' to '%s'."), $source, $target));
            return false;
        }

        return true;
    }


    /**
     * Checks the current situation of target folder, according
     * to the setting given in $updateFolders.
     *
     * NOTE: Condition mismatch is treated as "STATUS_PBC".
     * This allows an operator to clear all reported warnings/errors before resetting
     * the Item, as the task will try to process all subfolders and report *all*
     * possibly mismatching entries.
     *
     * $updateFolders must contain a valid option from self::$OPT_UPDATE_FOLDERS.
     * @see self::isValidUpdateFolders()
     */
    protected function checkTargetFolderCondition($targetFolder, $updateFolders)
    {
        $l = $this->logger;

        if (empty($targetFolder))
        {
            $l->logError(_('checkTargetFolderCondition: Empty target folder given.'));
            // TODO: Actually, this should not happen - so it should be an exception, rather than task-error?
            $this->setStatusError();
            return false;
        }

        if (empty($updateFolders))
        {
            $l->logError(_('checkTargetFolderCondition: $updateFolders empty.'));
            // TODO: Actually, this should not happen - so it should be an exception, rather than task-error?
            $this->setStatusConfigError();
            return false;
        }

        // The target may *never* be a file. Regardless of $updateFolders setting:
        if (file_exists($targetFolder))
        {
            $l->logInfo(sprintf(_("Target folder '%s' already exists."), $targetFolder));

            if (!is_dir($targetFolder))
            {
                $l->logError(sprintf(_("Target folder '%s' is a file, but must be a folder. Not good."), $targetFolder));
                $this->setStatusError();
                return false;
            }

            if (!is_writable($targetFolder))
            {
                $l->logError(sprintf(_("Target folder '%s' is not writable. Check access rights?"), $targetFolder));
                $this->setStatusError();
                return false;
            }
        }

        switch ($updateFolders)
        {
            // ----------------------------------
            case self::OPT_CREATE:
                if (file_exists($targetFolder))
                {
                    $l->logError(sprintf(
                                _("Folder '%s' already exists. This violates config condition '%s = %s'."),
                                $targetFolder,
                                self::CONF_UPDATE_FOLDERS,
                                $updateFolders));
                    $this->setStatusPBCT();
                    return false;
                }
                return true;
                break;

                // ----------------------------------
            case self::OPT_UPDATE:
                if (!file_exists($targetFolder))
                {
                    $l->logError(sprintf(
                                _("Target folder '%s' does not exist, but has to. This violates config condition '%s = %s'."),
                                $targetFolder,
                                self::CONF_UPDATE_FOLDERS,
                                $updateFolders));
                    $this->setStatusPBCT();
                    return false;
                }
                return true;
                break;

                // ----------------------------------
            default:
                // If option is valid should have been checked in "loadSettings()".
                throw new Exception(sprintf(
                            _("Invalid option for '%s': %s.\nThis should not have happened."),
                            self::CONF_UPDATE_FOLDERS,
                            $updateFolders
                            ));
        }

        $l->logError(_('checkTargetFolderCondition: No matching rule. This is odd and should not have happened.'));
        $this->setStatusError();
        return false;
    }


    /**
     * Similar to checkTargetFolderCondition(), but for target files instead of folders.
     * Scope is only one folder (current task's $CIFolder).
     *
     * @see self::OPT_UPDATE_FILES
     */
    protected function checkTargetFileCondition($targetFile, $updateFiles)
    {
        $l = $this->logger;

        // The target may *never* be a folder. Regardless of $updateFiles setting:
        if (file_exists($targetFile))
        {
            $l->logInfo(sprintf(_("Target file '%s' already exists."), $targetFile));

            if (is_dir($targetFile))
            {
                $l->logError(sprintf(_("Target '%s' is a folder, but should be a file. Not good."), $targetFile));
                $this->setStatusError();
                return false;
            }

            if (!is_writable($targetFile))
            {
                $l->logError(sprintf(_("Target '%s' is not writable. Check access rights?"), $targetFile));
                $this->setStatusError();
                return false;
            }
        }

        switch ($updateFiles)
        {
            case self::OPT_CREATE:
                if (file_exists($targetFile))
                {
                    $l->logError(sprintf(
                                _("File '%s' already exists. This violates config condition '%s = %s'."),
                                $targetFile,
                                self::CONF_UPDATE_FILES,
                                $updateFiles));
                    $this->setStatusPBCT();
                    return false;
                }
                return true;
                break;

            case self::OPT_UPDATE:
                if (!file_exists($targetFile))
                {
                    $l->logError(sprintf(
                                _("File '%s' does NOT exist, but has to. This violates config condition '%s = %s'."),
                                $targetFile,
                                self::CONF_UPDATE_FILES,
                                $updateFiles));
                    $this->setStatusPBCT();
                    return false;
                }
                return true;
                break;

            case self::OPT_CREATE_OR_UPDATE:
                // Target files CAN exist, but don't have to. Files are either created or overwritten.
                return true;
                break;

            default:
                // If option is valid should have been checked in "loadSettings()".
                throw new Exception(sprintf(
                            _("Invalid option for '%s': %s.\nThis should not have happened."),
                            self::CONF_UPDATE_FILES,
                            $updateFiles
                            ));
        }

        $l->logError(_('checkTargetFileCondition: No matching rule. This is odd and should not have happened.'));
        $this->setStatusError();
        return false;
    }



}

?>
