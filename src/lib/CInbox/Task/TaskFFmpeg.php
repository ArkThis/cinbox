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
use \Exception as Exception;


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
 *  - <a href="http://www.ArkThis.com/">ArkThis AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
class TaskFFmpeg extends TaskExec
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    const TASK_LABEL = 'Execute FFmpeg for media conversion';   ///< Human readable task name/label.

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
    const TODO_RECIPE = 'recipe';                       ///< Array key for recipe in $todoList.
    const TODO_IN = 'in';                               ///< Array key for input files in $todoList.
    const TODO_OUT = 'out';                             ///< Array key for output files in $todoList.
    const TODO_VALIDATE = 'validate';                   ///< Array key for flag "to validate or not".
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
    private $sources;                                   ///< @see #CONF_SOURCES
    private $targets;                                   ///< @see #CONF_TARGETS
    private $recipes;                                   ///< @see #CONF_RECIPES
    private $validates;                                 ///< @see #CONF_VALIDATES

    private $hashTypesAllowed;                          ///< List of which content hash algorithms/types are available.
    protected $hashType;                                ///< Selected content hash algorith/type.
    //@}

    /**
     * @name Prepared "to-do" list
     * Recipes and their files to process. Files resolved and ready to use with
     * convertFile().
     */
    //@{
    private $todoList;                                  ///< Array with all information to start transcoding call.
    private $filesIn;                                   ///< @see resolveInOut()
    private $filesOut;                                  ///< @see resolveInOut()
    //@}



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

        // This is used for calling external tool later:
        $this->exec = new CIExec();

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

        // Check if provided hashcode algorithm type is supported:
        if (!$this->hashTypeIsAllowed($hashType)) return false;
        $this->hashType = $hashType;


        // -------
        $this->sources = $config->get(self::CONF_SOURCES);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->sources)) return $this->skipIt();
        if (!$this->optionIsArray($this->sources, self::CONF_SOURCES)) return false;
        $l->logDebug(sprintf(
                    _("Patterns for FFmpeg sources (input): %s"),
                    implode(', ', $this->sources)
                    ));

        // -------
        $this->targets = $config->get(self::CONF_TARGETS);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->targets)) return $this->skipIt();
        if (!$this->optionIsArray($this->targets, self::CONF_TARGETS)) return false;
        $l->logDebug(sprintf(
                    _("Patterns for FFmpeg targets (output): %s"),
                    implode(', ', $this->targets)
                    ));

        // -------
        $this->recipes = $config->get(self::CONF_RECIPES);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->recipes)) return $this->skipIt();
        if (!$this->optionIsArray($this->recipes, self::CONF_RECIPES)) return false;
        $l->logDebug(sprintf(
                    _("Commandline 'recipes' for FFmpeg: %s"),
                    implode(', ', $this->recipes)
                    ));

        // -------
        $this->validates = $config->get(self::CONF_VALIDATES);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->validates)) return $this->skipIt();
        if (!$this->optionIsArray($this->validates, self::CONF_VALIDATES)) return false;
        $l->logDebug(sprintf(
                    _("Rewrap/transcoding Hash validate enabled: %s"),
                    implode(', ', $this->validates)
                    ));

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

        $l = $this->logger;

        // Check if we have a proper temp folder:
        if (!$this->checkTempFolder()) return false;


        // INFO: The check if FFmpeg binary (extract from recipe) exists and is
        //       valid is done before executing each command/recipe in run().

        // Check if config-tuples are complete:
        try
        {
            $this->checkArrayKeysMatch2(array(
                        self::TODO_IN => $this->sources,
                        self::TODO_OUT => $this->targets,
                        self::TODO_RECIPE => $this->recipes,
                        self::TODO_VALIDATE => $this->validates
                        ));
        }
        catch (Exception $e)
        {
            $l->logException(sprintf(_("Not all FFmpeg config options are configured. Please check config file!")), $e);
            $this->setStatusConfigError();
            return false;
        }

        // Resolve actual/final FFmpeg commandline strings from recipe+files_in+files_out:
        $this->todoList = $this->createTodoList(
                $this->sources, $this->targets, $this->recipes, $this->validates
                );
        if ($this->todoList === false)
        {
            $this->setStatusConfigError();
            return false;
        }

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
        //   * Iterate convertFile() call with values from resolveInOut().

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
            $recipe = $todo[self::TODO_RECIPE];
            $filesIn = $todo[self::TODO_IN];
            $filesOut = $todo[self::TODO_OUT];
            $validate = $todo[self::TODO_VALIDATE];

            foreach ($filesIn as $key=>$fileIn)
            {
                $fileOut = $filesOut[$key];
                $count++;
                // TODO: Add hash generating code to recipe:
                // if ($validate) $recipe = prepareValidation($recipe);

                if ($this->convertFile($recipe, $fileIn, $fileOut) != CIExec::EC_OK) $error++;

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
     * Checks if the given arrays have matching keys - in identical order!
     * @throws Exception    If keys/count does /not/ match.
     *
     * This function is used to check if each ffmpeg-to-do-tuple in the config
     * (consisting of: in, out, recipe, validation) is complete.
     *
     * It does that by checking if all config option arrays have the same
     * number of elements. The shortest arrays are possibly lacking elements.
     *
     * Therefore $sizes returns an associative array of this form:
     *   'array_name' => (int)count
     *
     * This can be used to provide information which of the given arrays
     * should be checked. This requires that 'array_name' is given as key for
     * each provided array in $arrays.
     *
     * @param $arrays   [array]     Each element is an array to compare.
     */
    protected function checkArrayKeysMatch($arrays, &$sizes)
    {
        // For logging shortest array:
        $sizes = array();
        $mismatch = 0;

        $keys1 = null; // Previous array's keys to compare with.
        foreach ($arrays as $name=>$a)
        {
            if (!is_null($keys1))
            {
                $keys2 = array_keys($a);
                if ($keys1 !== $keys2) $mismatch++;
            }
            $keys1 = array_keys($a);
            $sizes[$name] = count($a);
        }

        // Find the weakest link:
        asort($sizes);

        if ($mismatch > 0)
        {
            throw new \Exception(sprintf(
                    _("Arrays are not aligned: keys are not present at the same position! Please check in the following order: '%s'"),
                    implode(', ', array_keys($sizes))
                    ));
            return false;
        }

        return true;
    }


    /**
    * Identical to checkArrayKeysMatch(), but without return info about which
    * arrays had which size.
    */
    protected function checkArrayKeysMatch2($arrays)
    {
        $sizes = array();
        return $this->checkArrayKeysMatch($arrays, $sizes);
    }


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
    protected function convertFile($recipe, $sourceFile, $targetFile)
    {
        $l = $this->logger;

        $logFile = $this->getCmdLogfile();
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
                __FF_HASH_V__ => $hashRecipes[self::HASH_VIDEO],
                __FF_HASH_A__ => $hashRecipes[self::HASH_AUDIO],
                );

print_r($arguments); //DELME
        $command = $this->resolveCmd($recipe, $arguments);

        // Bail out if command string seems invalid:
        if (!$this->isCmdValid($command)) return false;

        $l->logNewline();
        $l->logMsg(sprintf(_("FFmpeg processing '%s'..."), $sourceFile));
        $l->logInfo(sprintf(_("FFmpeg command: %s"), $command));

        // -------------------
        // Makes sense to store the command to the logfile, in case something goes wrong:
        // Writing it /before/ execution so it's logged even in case of a complete crash.
        $this->writeToCmdLogfile(
                sprintf(_("Command line and complete, uncut console output:\n\n%s\n\n"), $command),
                $logFile);

        // This is where the command actually gets executed!
        $exitCode = $this->exec->execute($command);
        // -------------------

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
                        $logFile));
        }

        return $exitCode;
    }


    /**
     * Returns array with files/folders within the folder that match
     * the glob patterns in '$patterns'.
     * Relative paths are resolved to their absolute equivalents.
     *
     * This function is copied from 'TaskFilesMatch' class, but improved
     * here since then.
     * TODO: Move common part to Helper class?
     */
    protected function getMatchingFiles($folder, $patterns)
    {
        $l = $this->logger;

        if (empty($patterns)) return array();

        $source = Helper::getAbsoluteName($folder) . DIRECTORY_SEPARATOR;

        $matching = array();
        foreach ($patterns as $pattern)
        {
            $result = glob($source . $pattern);
            $matching = array_merge($matching, $result);
        }

        return $matching;
    }


    /**
     * Iterates through options set in '$sources' and resolves it
     * to a single array, where each entry is a single file.
     *
     * This is used to resolve filemasks (e.g. *.avi, *.mkv, etc), but
     * compatible to setting wildcard-free filenames explicitely in '$sources'.
     *
     * At the same time, it Iterates through options set in '$targets'
     * and resolves it to a single array, where each entry is also a single
     * filename.
     *
     * $sources and $targets /must/ always have the same number
     * of entries in matching order, as each IN will be matched to its
     * corresponding OUT setting.
     */
    protected function resolveInOut($source, $target)
    {
        $l = $this->logger;
        $config = $this->config;

        // Avoid leftovers:
        $this->filesIn = array();
        $this->filesOut = array();
        $filesIn = array();
        $filesOut = array();

        // getMatchingFiles() expects an array, so we wrap '$source' as one:
        $filesIn = $this->getMatchingFiles($this->sourceFolder, array($source));
        if (empty($filesIn))
        {
            $l->logMsg(sprintf(
                        _("No files matching '%s'. That's okay."),
                        $source));
            // It's not an error, so we return okay:
            return true;
        }
        $l->logMsg(sprintf(
            _("Found %d source files matching '%s'."),
            count($filesIn),
            $source));

        foreach ($filesIn as $key=>$fileIn)
        {
            $arguments = array(
                    __FILE_IN__ => $fileIn,
                    __FILE_IN_NOEXT__ => Helper::getBasename($fileIn, $suffix=false),
                    );

            $fileOut = $config->resolveString($target, $arguments);
            // Resolve relative filepaths to their absolute location relative
            // to the current sourceFolder:
            $fileOut = Helper::getAbsoluteName($fileOut, $this->sourceFolder);

            // Explicitely using $key to make sure $filesIn stay sync with $filesOut:
            $filesOut[$key] = $fileOut;

            $l->logDebug(sprintf(
                _("Source file: '%s' => Target file: '%s'\n"),
                $fileIn,
                $fileOut));
        }

        // Check if all sources have received matching targets and
        // report an error otherwise:
        try
        {
            if ($this->checkArrayKeysMatch2(array($filesIn, $filesOut)))
            {
                $this->filesIn = $filesIn;
                $this->filesOut = $filesOut;
                return true;
            }
        }
        catch (Exception $e)
        {
            $l->logError(sprintf(
                _("Not all input files have matching output files:\n%s\n%s\n"),
                print_r($filesIn, true),
                print_r($filesOut, true)
                ));
            $this->setStatusConfigError();
            return false;
        }

        return false;
    }


    /**
     * Checks if any of the files listed in $targets exists and sets the
     * task status to "problem but continue task".
     */
    protected function checkTargetExists($targets)
    {
        $l = $this->logger;

        if (!is_array($targets))
        {
            throw new \Exception(
                    sprintf(_("Unable to check if targets exist: file list is not an array, but of type '%s'"),
                    gettype($targets)
                        ));
            return false;
        }

        $count = 0;
        foreach ($targets as $key=>$target)
        {
            if (file_exists($target))
            {
                $count++;
                $l->logError(sprintf(
                            _("Target file already exists: '%s'. Remove it first."),
                            $target
                            ));
            }
        }

        if ($count > 0)
        {
            $this->setStatusError();
            $l->logError(sprintf(
                _("Could not run FFmpeg: %d/%d target files already exist."),
                $count,
                count($targets)
            ));
            return false;
        }

        return true;
    }


    /**
     * Aligns recipes with resolved sources and targets ( @see resolveInOut() ).
     * Output is a 'to-do list' where each recipe has its list of files to
     * read and generate in a ready-to-use way for passing it to convertFile().
     */
    protected function createTodoList($sources, $targets, $recipes, $validates)
    {
        $todoList = array();                // Clean and fresh start.

        foreach ($recipes as $key=>$recipe)
        {
            // All keys of $sources, $targets, $recipes, etc must be aligned
            // for this to work properly:
            $source = $sources[$key];
            $target = $targets[$key];
            $validate = $validates[$key] == 1 ? true : false; // It's a bool! ;)

            // Get the actual filenames for source and target files:
            if (!$this->resolveInOut($source, $target)) return false;

            // Nothing to do, but it's okay.
            if (empty($this->filesIn) or empty($this->filesOut)) continue;

            // Go to error if any target file already exists:
            if (!$this->checkTargetExists($this->filesOut)) return false;

            $todoList[] = array(
                    self::TODO_RECIPE => $recipe,
                    self::TODO_IN => $this->filesIn,
                    self::TODO_OUT => $this->filesOut,
                    self::TODO_VALIDATE => $validate,
                    );
        }

        return $todoList;
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
                        implode(' ', $this->hashTypesAllowed)));
        }

        return true;
    }

    //@}



}

?>
