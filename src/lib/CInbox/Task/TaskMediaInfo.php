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
use \ArkThis\CInbox\CIExec;         // for running external commands
use \ArkThis\Helper;
use \Exception as Exception;
use \ArkThis\CInbox\Task\AbstractTaskExecFF;


/**
 * @author Peter Bubestinger-Steindl (cinbox (at) ArkThis.com)
 * @copyright
 *  Copyright 2025 ArkThis AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.ArkThis.com/products/cinbox/">CInbox product website</a>
 *  - <a href="http://www.ArkThis.com/">AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 *
 *  This Task is intended to call MediaInfo on media files and create files
 *  containing detailed (tech-)metada information about those files.
 */
class TaskMediaInfo extends AbstractTaskExecFF
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    const TASK_LABEL = 'Run MediaInfo'; ///< Human readable task name/label.

    /**
     * @name Task Settings
     * Names of config settings used by this task
     */
    //@{
    // The following arrays must have identical entries, that are related to each other.
    const CONF_SOURCES = "MEDIAINFO_IN";         ///< Glob patterns to match (media source files)
    const CONF_TARGETS = "MEDIAINFO_OUT";        ///< Target output filenames (per match)
    const CONF_RECIPES = "MEDIAINFO_RECIPE";     ///< Commandline to call
    //@}

    //@{
    // Constants used within this class.
    // TODO?
    //@}

    /* ========================================
     * PROPERTIES
     * ======================================= */

    // Class properties are defined here.
    // Basic ones like $recipes, $sources and $targets are inherited.


    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

        // This is used for calling external tool later:
        $this->exec = new CIExec();     // class property provided by TaskExec.
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
        // Disabled, because FFmpeg Task is too specific in settings.
        // Collides with deriving methods/properties from it.
        // Still makes sense to re-use ffmpeg helper stuff like source/target resolution, etc.
        #if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        #$l->logLine(42, '🌟️ -');

        // -------
        // Load media source list:
        // TODO: do some checking on the patterns?
        $this->sources = $config->get(self::CONF_SOURCES);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->sources)) return $this->skipIt("sources empty");
        // TODO: throw InvalidArgumentException?
        if (!$this->optionIsArray($this->sources, self::CONF_SOURCES)) return false;
        $l->logDebug(sprintf(
                    _("Patterns for MediaInfo sources (input): %s"),
                    implode(', ', $this->sources)
                    ));

        // -------
        // Load target list:
        $this->targets = $config->get(self::CONF_TARGETS);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->targets)) return $this->skipIt("targets empty");
        // TODO: throw InvalidArgumentException?
        if (!$this->optionIsArray($this->targets, self::CONF_TARGETS)) return false;
        $l->logDebug(sprintf(
                    _("List of MediaInfo targets (output): %s"),
                    implode(', ', $this->targets)
                    ));

        // -------
        // Load recipe list:
        $this->recipes = $config->get(self::CONF_RECIPES);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->recipes)) return $this->skipIt("recipes empty");
        // TODO: throw InvalidArgumentException?
        if (!$this->optionIsArray($this->recipes, self::CONF_RECIPES)) return false;
        $l->logDebug(sprintf(
                    _("List of MediaInfo recipes: %s"),
                    implode(', ', $this->recipes)
                    ));

        // -------
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

        // TODO: Add here what the task is supposed to do.
        //   * Execute MediaInfo calls
        //   * Handle !=0 exit codes

        $count = 0;
        $error = 0;

        if (empty($this->todoList))
        {
            // Nothing to do, but it's okay.
            $l->logMsg(sprintf(_("No files found. Nothing done. That's okay.")));
            $this->setStatusDone();
            return true;
        }

        foreach ($this->todoList as $todo)
        {
            $recipe = $todo[self::TODO_RECIPES];
            $filesIn = $todo[self::TODO_IN];
            $filesOut = $todo[self::TODO_OUT];

            foreach ($filesIn as $key=>$fileIn)
            {
                $fileOut = $filesOut[$key];
                $count++;

                if ($this->runRecipes($recipe, $fileIn, $fileOut) != CIExec::EC_OK) $error++;
                // TODO:
                // Handle MediaInfo policy FAIL:
            }
            $l->logNewline();
        }

        if ($count > 0)
        {
            $l->logMsg(sprintf(_("Processed %d files."), $count));
            $l->logNewline();
        }

        if ($error > 0)
        {
            $this->setStatusPBCT();
            $l->logError(sprintf(_("Error processing %d files."), $error));
            $l->logNewline();
            return false;
        }

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
    

    /**
     * Iterate over all recipes and source/target file arrays and run each one
     * of them.
     */
    protected function runRecipes($recipe, $sourceFile, $targetFile)
    {
        $l = $this->logger;
        $config = $this->config;

        $logFile = $this->createCmdLogFilename();

        // TODO: Idea! Add method that resolves flavors of filename
        // (with/without suffix, path, etc) and returns it as ready-to-use
        // $arguments array?
        $arguments = array(
                __FILE_IN__ => $sourceFile,
                __FILE_OUT__ => $targetFile,
                __FILE_IN_NOEXT__ => Helper::getBasename($sourceFile, $suffix=false),
                __FILE_OUT_NOEXT__ => Helper::getBasename($targetFile, $suffix=false),
                __DIR_IN__=> dirname($sourceFile),
                __DIR_OUT__=> dirname($targetFile),
                __LOGFILE__ => $logFile,
                );
        #print_r($arguments); //DELME

        $command = $this->resolveCmd($recipe, $arguments);

        // Bail out if command string seems invalid:
        if (!$this->isCmdValid($command)) return false;

        $l->logNewline();
        $l->logMsg(sprintf(_("MediaInfo processing '%s'..."), $sourceFile));
        $l->logInfo(sprintf(_("MediaInfo command: %s"), $command));

        // -------------------
        // Makes sense to store the command to the logfile, in case something goes wrong:
        // Writing it /before/ execution so it's logged even in case of a complete crash.
        $this->writeToCmdLogfile(sprintf(
            _("Command line and complete, uncut console output:\n\n%s\n\n"),
            $command),
        $logFile
        );

        // This is where the command actually gets executed!
        $exitCode = $this->exec->execute($command);
        // NOTE: This currently only checks the exit-code of $command, but NOT
        // the PASS/FAIL status of MediaInfo policies. This must happen
        // *after* this execution call of $command (=MediaInfo).
        // -------------------

        if ($exitCode == CIExec::EC_OK)
        {
            $l->logMsg(sprintf(
                _("Command ran well (logfile was: %s)"),
                $logFile
            ));

            // If things went fine, shall we remove the logfile?
            // TODO: re-enable $this->removeCmdLogfile($logFile);
            // Or rather handle this by CInbox's item-garbage collection?
        }
        else
        {
            $this->setStatusPBCT();
            // TODO: If this happens, the target file should be deleted to avoid leftovers.

            $l->logNewline();
            $l->logError(sprintf(
                _("MediaInfo command returned exit code '%d'.\nFor details see logfile: '%s'"),
                $exitCode,
                $logFile
            ));
        }

        return $exitCode;
    }

    //@}

}

?>
