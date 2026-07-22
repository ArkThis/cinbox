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
use \ArkThis\CInbox\Task\AbstractTaskExecFF;


/**
 * Task for executing FFmpeg to convert media files.
 * Simple version.
 *
 * Allows: 1 input, 1 output file per recipe.
 * For more complex FFmpeg-foo, it's probably better to create a separate task
 * type.
 *
 * TODO:
 *   - What about some form of presets for target formats/recipes?
 *
 *
 * @author Peter Bubestinger-Steindl (cinbox (at) ArkThis.com)
 * @copyright
 *  Copyright 2019 ArkThis AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.ArkThis.com/products/cinbox/">CInbox product website</a>
 *  - <a href="https://github.com/ArkThis/cinbox/">CInbox source code</a>
 *  - <a href="http://www.ArkThis.com/">ArkThis AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
class TaskFFmpeg extends AbstractTaskExecFF
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    const TASK_LABEL = 'Run FFmpeg for media conversion';   ///< Human readable task name/label.

    /**
     * @name Task Settings
     * Names of config settings used by this task
     */
    //@{
    const CONF_SOURCES = "FFMPEG_IN";                   ///< Filenames or filemasks to process as input.
    const CONF_TARGETS = "FFMPEG_OUT";                  ///< Output filenames (may include path).
    const CONF_RECIPES = "FFMPEG_RECIPE";               ///< Commandline "recipes" to execute FFmpeg transcoding, etc. This includes path+name of FFmpeg binary.
    //@}

    /**
     * @name Array keys To-Do List
     */
    //@{
    const TODO_VALIDATES = 'validate';                   ///< Array key for flag "to validate or not".
    //@}


    /* ========================================
     * PROPERTIES
     * ======================================= */

    /**
     * @name Setting variables
     * For storing settings read from the config file:
     */

    // Check class AbstractTaskExecFF for common settings.


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
        //   * Execute FFmpeg calls
        //   * Handle !=0 exit codes
        //   * Iterate runRecipes() call with values from resolveInOut().

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
            //$validate = $todo[self::TODO_VALIDATES];

            foreach ($filesIn as $key=>$fileIn)
            {
                $fileOut = $filesOut[$key];
                $count++;

                if ($this->runRecipes($recipe, $fileIn, $fileOut) != CIExec::EC_OK) $error++;

                // TODO: Evaluate content hashes...
            }
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

        // TODO (optional)

        // Must return true on success:
        return true;
    }

    //@}


    /**
     * @name Task-specific methods
     */
    //@{

    // Default type is 'protected'. Use 'public' functions only where necessary.

    /**
     * Converts a single media file from A to B.
     * Uses external command to do this.
     *
     * @param $recipe       [string]    Commandline recipe with placeholders.
     * @param $sourceFile   [string]    Source (input) media file to be read.
     * @param $targetFile   [string]    Target (output) media file to be created.
     *
     * @retval integer/bool
     *  Exit code (integer) of executed FFmpeg command (derived from recipe).
     *  'False' if any other error occurred.
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
        #print_r($arguments); //DEBUG
        $config->addPlaceholders($arguments);
        // TODO ----------------- Move all of this to common ancestor class! [END]

        // Here's where the recipe is called to life!
        $exitCode = $this->runRecipe($recipe);

        if ($exitCode == CIExec::EC_OK)
        {
            // Things went fine, let's remove the logfile:
            $this->removeCmdLogfile($logFile);
        }
        else
        {
            $this->setStatusPBCT();
            // TODO: If this happens, the target file should be deleted to avoid leftovers.

            $l->logNewline();
            $l->logError(sprintf(
                _("FFmpeg command returned exit code '%d'.\nFor details see logfile: '%s'"),
                $exitCode,
                $logFile
            ));
        }

        return $exitCode;
    }

    //@}



}

?>
