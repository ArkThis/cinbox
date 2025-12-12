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
 * Task for calling external commands that work with the following arguments:
 *
 * 1 output for 1 input file per 1 "recipe" (=command + parameters call)
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
abstract class AbstractTaskExecFF extends TaskExec
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    //const TASK_LABEL = 'Basic concept for calling in/out/recipe tools';   ///< Human readable task name/label.

    /**
     * @name Task Settings
     * Names of config settings used by this task
     */
    //@{
    const CONF_SOURCES = "FF_IN";                   ///< Filenames or filemasks to process as input
    const CONF_TARGETS = "FF_OUT";                  ///< Output filenames (may include path)
    const CONF_RECIPES = "FF_RECIPE";               ///< Commandline "recipes" to call
    //@}

    /**
     * @name Array keys To-Do List
     */
    //@{
    const TODO_IN = 'in';                               ///< Array key for input files in $todoList.
    const TODO_OUT = 'out';                             ///< Array key for output files in $todoList.
    const TODO_RECIPES = 'recipe';                       ///< Array key for recipe in $todoList.
    //@}


    /* ========================================
     * PROPERTIES
     * ======================================= */

    /**
     * @name Setting variables
     * For storing settings read from the config file:
     */
    //@{
    protected $sources;                                 ///< @see #CONF_SOURCES
    protected $targets;                                 ///< @see #CONF_TARGETS
    protected $recipes;                                 ///< @see #CONF_RECIPES
    //@}

    /**
     * @name Prepared "to-do" list
     * Recipes and their files to process. Files resolved and ready to use with
     * whatever is called in run().
     */
    //@{
    protected $todoList;                                ///< Array with all information to start transcoding call.
    protected $filesIn;                                 ///< @see resolveInOut()
    protected $filesOut;                                ///< @see resolveInOut()
    //@}



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, static::TASK_LABEL);

        // This is used for calling external tool later:
        $this->exec = new CIExec();
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
        $this->sources = $config->get($this::CONF_SOURCES);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->sources)) return $this->skipIt(_("No input sources given."));
        if (!$this->optionIsArray($this->sources, static::CONF_SOURCES)) return false;
        $l->logDebug(sprintf(
                    _("Patterns for sources (input): %s"),
                    implode(', ', $this->sources)
                    ));

        // -------
        $this->targets = $config->get(static::CONF_TARGETS);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->targets)) return $this->skipIt(_("No output targets given."));
        if (!$this->optionIsArray($this->targets, static::CONF_TARGETS)) return false;
        $l->logDebug(sprintf(
                    _("Patterns for targets (output): %s"),
                    implode(', ', $this->targets)
                    ));

        // -------
        $this->recipes = $config->get(static::CONF_RECIPES);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->recipes)) return $this->skipIt(_("No commandline recipes given."));
        if (!$this->optionIsArray($this->recipes, static::CONF_RECIPES)) return false;
        $l->logDebug(sprintf(
                    _("Commandline 'recipes': %s"),
                    implode(', ', $this->recipes)
                    ));

        // Must return true on success:
        return true;
    }

    /**
     * Resolve source/target file mask patterns to to-do lists, containing the
     * resolved filenames.
     * This is here separately and pretty unspecific to the $inputs.
     * Resolving of placeholders happens in createTodoList(), so there are
     * must-have array keys defined to work properly. (such as static::TODO_IN,
     * etc)
     *
     */
    public function populateTodoList($inputs)
    {
        // Resolve actual/final commandline strings from recipe+files_in+files_out:
        $this->todoList = $this->createTodoList($inputs);

        if ($this->todoList === false)
        {
            $this->setStatusConfigError();
            return false;
        }
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


        // TODO: The check if required binaries (extract from recipe) exists and is
        //       valid is done before executing each command/recipe in run().

        // Check if config-tuples are complete:
        try
        {
            $this->checkArrayKeysMatch2(array(
                static::TODO_IN => $this->sources,
                static::TODO_OUT => $this->targets,
                static::TODO_RECIPES => $this->recipes,
            ));
        }
        catch (Exception $e)
        {
            $l->logException(sprintf(
                _("Not all config options for Task '%s' are configured. Please check config file!"),
                get_class($this)
            ), $e);
            $this->setStatusConfigError();
            return false;
        }

        // Map config setting placeholder-using config strings:
        $this->populateTodoList(
            array(
                static::TODO_IN => $this->sources,
                static::TODO_OUT => $this->targets,
                static::TODO_RECIPES => $this->recipes,
            )
        );

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
     * This function is used to check if each recipe-to-do-tuple in the config
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
        $l = $this->logger;

        // For logging shortest array:
        $sizes = array();
        $mismatch = 0;

        $l->logDebug(sprintf(
            _("Matching keys for arrays: %s\n"),
            print_r($arrays, true)
        ));

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
            $source
        ));

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
                $fileOut
            ));
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
            throw new \Exception(sprintf(
                _("Unable to check if targets exist: file list is not an array, but of type '%s'"),
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
                _("Could not run command: %d/%d target files already exist."),
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
     *
     * The following /hardcoded/ array keys are vitally necessary and
     * *that MUST be set* to function properly:
     *
     * static::TODO_RECIPES => static::CONF_RECIPES
     * static::TODO_IN => static::CONF_SOURCES
     * static::TODO_OUT => static::CONF_TARGETS
     *
     * This is still pretty generic and useful for calling other programs, too.
     * (example: MediaConch or MediaInfo)
     * Other, task-specific array keys must be resolved separately.
     * This allows this method to be reused by object child classes.
     *
     * @param[in] array of arrays   $inputs An array with named keys (See 'TODO_' const's).
     */
    protected function createTodoList($inputs)
    {
        $l = $this->logger;

        if (empty($inputs) or (!is_array($inputs)))
        {
            throw new InvalidArgumentException(
                _("inputs were empty or not an array. Expected are INI-file config variables with file-patterns/names"
                ));
        }

        // Extract to local variables, for easier handling and shorter code:
        $recipes = $inputs[static::TODO_RECIPES];
        $sources = $inputs[static::TODO_IN];
        $targets = $inputs[static::TODO_OUT];
        // NOTE: Not checking them here if(empty()) or not. FIXME?

        // Plain debug info:
        $l->logDebug(sprintf(
            _("%s recipes: %s\n"),
            get_class($this),
            print_r($recipes, true)
        ));
        $l->logDebug(sprintf(
            _("%s sources: %s\n"),
            get_class($this),
            print_r($sources, true)
        ));
        $l->logDebug(sprintf(
            _("%s targets: %s\n"),
            get_class($this),
            print_r($targets, true)
        ));

        $todoList = array();                // Clean and fresh start.

        // Resolve in/out file patterns for each **recipe**.
        // This allows to execute multiple recipe calls in sequence.
        // Possibly even daisy-chaining outputs from one as input to the next.
        foreach ($recipes as $key=>$recipe)
        {
            // All keys of $sources, $targets, $recipes, etc must be aligned
            // for this to work properly:
            $source = $sources[$key];
            $target = $targets[$key];

            $l->logInfo(sprintf(
                _("Resolving in/out patterns for recipe %s:\n%s\nSource: %s\nTarget: %s\n\n"),
                $key,
                $recipe,
                $source,
                $target
            ));

            // task-specific parameters, must be resolved separately. Sorry.
            #$validate = $validates[$key] == 1 ? true : false; // It's a bool! ;)

            // Get the actual filenames for source and target files:
            if (!$this->resolveInOut($source, $target))
            {
                throw new RuntimeException(sprintf(
                    _("Something went wrong with resolving filename patterns of source/target settings.\nCheck source='%s'\n\nand target='%s'\n\n",
                    print_r($source, true),
                    print_r($target, true))
                ));
            }

            // Nothing to do, but it's okay.
            if (empty($this->filesIn) or empty($this->filesOut)) continue;

            // Go to error if any target file already exists:
            // DISABLED. Reason: Should be up to the user. use the called
            // tool's option to handle existing files.
            #if (!$this->checkTargetExists($this->filesOut)) return false;

            // Put the resolved command tuples ready for further batch-processing:
            $todoList[] = array(
                    static::TODO_RECIPES => $recipe,
                    static::TODO_IN => $this->filesIn,
                    static::TODO_OUT => $this->filesOut,
                    );

            $l->logDebug(sprintf(
                _("Recipe TODO list for this task: %s\n"),
                print_r($todoList, true)
            ));
        }

        return $todoList;
    }

    //@}



}

?>
