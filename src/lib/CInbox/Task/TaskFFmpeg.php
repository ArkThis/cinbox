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
    const CONF_HASH_TYPE = "FFMPEG_HASH_TYPE";          ///< Algorithm for content hash.
    const CONF_SOURCES = "FFMPEG_IN";                   ///< Filenames or filemasks to process as input.
    const CONF_TARGETS = "FFMPEG_OUT";                  ///< Output filenames (may include path).
    const CONF_RECIPES = "FFMPEG_RECIPE";               ///< Commandline "recipes" to execute FFmpeg transcoding, etc. This includes path+name of FFmpeg binary.
    const CONF_VALIDATES = "FFMPEG_VALIDATE";           ///< Flag to enable content hash validation. Used to confirm lossless codec or container changes.
    //@}

    /**
     * @name Array keys To-Do List
     */
    //@{
    const TODO_VALIDATES = 'validate';                   ///< Array key for flag "to validate or not".
    //@}

    /**
     * @name Content hash
     * Constants related to content hashing.
     */
    //@{
    /* Command recipe for content hash (video only) */
    const HASH_RECIPEMASK_V = '-f hash -hash %s -an %s';
    /* Command recipe for content hash (audio only) */
    const HASH_RECIPEMASK_A = '-f hash -hash %s -vn %s';

    const HASH_VIDEO = 'video';                         ///< Array key for video-related content hash.
    const HASH_AUDIO = 'audio';                         ///< Array key for audio-related content hash.
    const HASH_FILEMASK_V = '%s.v.%s';                  ///< Filemask for video content hash.
    const HASH_FILEMASK_A = '%s.a.%s';                  ///< Filemask for audio content hash.
    //@}


    /* ========================================
     * PROPERTIES
     * ======================================= */

    /**
     * @name Setting variables
     * For storing settings read from the config file:
     */
    //@{
    private $validates;                                 ///< @see #CONF_VALIDATES

    private $hashTypesAllowed;                          ///< List of which content hash algorithms/types are available.
    private $hashType;                                  ///< Selected content hash algorithm/type.
    //@}



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

        // Set which content hash types are available (case insensitive).
        // See <a href="http://ffmpeg.org/ffmpeg-all.html#hash-1">FFmpeg documentation on "hash" muxer</a>
        // for details and options.
        $this->hashTypesAllowed = array(
                'md5', 'murmur3',
                'ripemd128', 'ripemd160', 'ripemd256', 'ripemd320',
                'sha160', 'sha224', 'sha256', 'sha512/224', 'sha512/256', 'sha384', 'sha512',
                'crc32', 'adler32');
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

        // -------
        // Load content hash algorithm type:
        $hashType = strtolower($config->get(self::CONF_HASH_TYPE));
        $l->logDebug(sprintf(_("Content hashcode type (algorithm): %s"), $hashType));

        //TODO: This is declaring a default. This should be done differently,
        //and possibly somewhere else...?
        if (empty($hashType)) $hashType = 'md5';

        // Check if provided hashcode algorithm type is supported:
        if (!$this->hashTypeIsAllowed($hashType)) return false;
        $this->hashType = $hashType;

        // -------
        /* This is currently NOT IMPLEMENTED YET.
         * And should definitely not be mandatory.
        $this->validates = $config->get(self::CONF_VALIDATES);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->validates)) return $this->skipIt();
        if (!$this->optionIsArray($this->validates, self::CONF_VALIDATES)) return false;
        $l->logDebug(sprintf(
                    _("Rewrap/transcoding Hash validate enabled: %s"),
                    implode(', ', $this->validates)
                    ));
         */

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
                // TODO: Add hash generating code to recipe:
                // if ($validate) $recipe = prepareValidation($recipe);

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

        $logFile = $this->createCmdLogFilename();
        $hashRecipes = $this->getHashRecipes($sourceFile);

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
                //__FF_HASH_V__ => $hashRecipes[self::HASH_VIDEO],
                //__FF_HASH_A__ => $hashRecipes[self::HASH_AUDIO],
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


    /**
     * Returns ready-to-use commandline recipe strings that create separate
     * content hash files for video and audio.
     */
    protected function getHashRecipes($fileName)
    {
        $hashType = $this->hashType;
        $hashFiles = $this->getHashTempFilenames(
                $fileName, $this->CIFolder, $hashType);

        $recipes = array(
                self::HASH_VIDEO => sprintf(self::HASH_RECIPEMASK_V,
                    $hashType,
                    $hashFiles[self::HASH_VIDEO]),

                self::HASH_AUDIO => sprintf(self::HASH_RECIPEMASK_A,
                    $hashType,
                    $hashFiles[self::HASH_AUDIO]),
                );

        return $recipes;
    }


    /**
     * Returns the filenames where the content hashcode is temporarily stored
     * for $fileName.
     * This method is static, so it can be used by other tasks to determine
     * where to find the temporary hashcode files.
     */
    public static function getHashTempFilenames($fileName, $CIFolder, $hashType)
    {
        // TODO: Replace this by $this->getTempFolder()?
        $tempFolder = $CIFolder->getTempFolder();
        $baseFolder = $CIFolder->getBaseFolder();

        // Recreate the same subfolder structure in temp-folder (by replacing
        // baseFolder substring with tempFolder), and then adding the hash-type
        // (algo) string as file suffix:
        $tempName = str_replace($baseFolder, $tempFolder, $fileName);

        $hashFiles = array(
                // For video content:
                self::HASH_VIDEO => sprintf(
                    self::HASH_FILEMASK_V,
                    $tempName, $hashType),

                // For audio content:
                self::HASH_AUDIO => sprintf(
                    self::HASH_FILEMASK_A,
                    $tempName, $hashType),
                );

        return $hashFiles;
    }


    /**
     * Returns an array containing the hash algorithm types allowed/supported.
     */
    public function getHashTypesAllowed()
    {
        return $this->hashTypesAllowed;
    }


    /**
    * Checks if provided hashtype is supported by this task.
    * Technically, all hash types offered by FFmpeg version used are allowed,
    * but $this->hashTypesAllowed needs to contain them as strings.
    */
    protected function hashTypeIsAllowed($hashType)
    {
        $hashType = strtolower($hashType);

        if (!in_array($hashType, $this->hashTypesAllowed))
        {
            throw new \Exception(sprintf(
                _("Hash type '%s' is invalid, not supported by FFmpeg or not known to this Task.\nValid types are: %s"),
                $hashType,
                implode(' ', $this->hashTypesAllowed)
            ));
        }

        return true;
    }

    //@}



}

?>
