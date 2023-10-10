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
use \Exception as Exception;


/**
 * Base class for Common Inbox tasks.
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
abstract class CITask
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Names of config settings used by a task must be defined here.

    /**
     * @name Task status
     * Available status options for task execution.
     *
     * @see
     *  #setStatus(), #setStatusUndefined(), #setStatusRunning(), #setStatusDone(),
     *  #setStatusPBCT(), #setStatusPBC(),
     *  setStatusError(), setStatusConfigError()
     */
    //@{
    const STATUS_UNDEFINED = 0;             ///< Undefined. Default unless task changes it.
    const STATUS_RUNNING = 1;               ///< Undefined, but indicates that the task is in progress.
    const STATUS_WAIT = 2;                  ///< Task decided that Item is not ready yet and shall be moved back to 'to-do'.
    const STATUS_DONE = 5;                  ///< Success! This means the task completed successfully.
    const STATUS_PBCT = 6;                  ///< There were problems, but the task may continue.
    const STATUS_PBC = 7;                   ///< There were problems, but subsequent task may continue.
    const STATUS_ERROR = 10;                ///< An error occurred. Abort execution as soon as possible.
    const STATUS_CONFIG_ERROR = 11;         ///< If a config option was not valid
    const STATUS_SKIPPED = 15;              ///< If task was skipped
    //@}


    /**
     * @name Task Modes
     * Different operational modes for a task.
     */
    //@{
    const ONCE_PER_ITEM = false;            ///< True: Run this task only once per item.
    const IS_RECURSIVE = false;             ///< Default (false): one task = one folder. True: The task performs actions on subfolders too.
    //@}


    /**
     * @name Task Settings
     * This setting is defined here, since it affects multiple tasks
     * if files are excluded from copy (HashGenerate, CopyToTarget, HashValidate, etc)
     */
    //@{
    const CONF_COPY_EXCLUDE = 'COPY_EXCLUDE';         ///< Patterns which files to exclude from copying.
    //@}


    /**
     * @name Miscellaneous
     * This setting is defined here, since it affects multiple tasks
     * that need to access the temporary target folder (before TaskRenameTarget has run).
     */
    //@{
    const MASK_TARGET_TEMP = 'temp_%s';
    //@}



    /* ========================================
     * PROPERTIES
     * ======================================= */

    /**
     * @name Basics
     */
    //@{
    protected $logger;                      ///< Logging handler.
    protected $name;                        ///< Class name of this task.
    protected $label;                       ///< Human readable Task label.
    protected $description;                 ///< Human readable description of Task's purpose/actions.
    //@}

    /**
     * @name Folder handling
     */
    //@{
    protected $CIFolder;                    ///< CIFolder object that provides all information needed for this task.
    protected $sourceFolder;                ///< Source folder of this task.
    protected $targetFolder;                ///< Target folder of this task (read from config).
    protected $targetFolderStage;           ///< Temporary name of Target folder of this task. Used until inbox processing completed successfully.
    protected $itemSubDirs;                 ///< Array of Item subfolders: key=foldername, value=CIFolder (initialized with its config).
    //@}

    /**
     * @name Status handling
     */
    //@{
    protected $skip = false;                ///< True: Skip execution. Default=false (=normal execution).
    protected $status;                      ///< Status of this task (=for current item subfolder).
    protected static $statusGlobal;         ///< Status of the sum of tasks of this type.      // TODO! Implement and use this.
    //@}

    /**
     * @name Temp folder handling
     */
    //@{
    protected $tempFolder;                  ///< Path of the temp folder for this task.
    //@}

    /**
     * @name Task config options
     * Variables that contain settings from the config file.
     */
    //@{
    protected $copyExclude;                 ///< Contains the value of CONF_COPY_EXCLUDE.
    //@}


    /*
TODO: Idea!
- static properties to store task-status of sub-tasks.
- finalize as static method to "sum up" the work of the whole task (not just sub-tasks)
     */



    /* ========================================
     * METHODS
     * ======================================= */


    function __construct(&$CIFolder, $label)
    {
        $this->logger = $CIFolder->getLogger();
        $this->config = $CIFolder->getConfig();

        $this->setName(get_class($this));
        $this->setLabel($label);

        if (!$CIFolder instanceof CIFolder)
        {
            throw new Exception(sprintf(_("Cannot instantiate task '%s': Invalid CIFolder object."), $this->name));
        }
        $this->CIFolder = $CIFolder;

        $this->status = self::STATUS_UNDEFINED;
        $this->setTempFolder();
    }


    /**
     * Sets the name of this task type.
     */
    protected function setName($name)
    {
        $this->name = $name;
        $this->config->addPlaceholder(__TASK_NAME__, $this->name);
    }

    /**
     * Returns the name of this task type.
     * This currently equals the class name.
     *
     * @see $name
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Set human readable label for this task.
     *
     * @see $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
        $this->config->addPlaceholder(__TASK_LABEL__, $this->label);
    }


    /**
     * Returns the human readable label of this task.
     *
     * @see $label
     */
    public function getLabel()
    {
        return $this->label;
    }


    /**
     * Set a description for this task.
     * What it's supposed to do, what is good to know, etc.
     *
     * Can be a multi-line string with linebreaks.
     *
     * @see $description
     */
    public function setDescription($description)
    {
        $this->description=$description;
    }


    /**
     * Returns a multi-line string describing this Task.
     *
     * @see $description
     */
    public function getDescription()
    {
        return $this->description;
    }


    /**
     * Returns 'true' if this task performs recursive actions or
     * 'false' if it only applies actions to the current folder.
     */
    public static function isRecursive()
    {
        return static::IS_RECURSIVE;
    }


    /**
     * Returns 'true' if this task is to be run only once per Item.
     */
    public static function oncePerItem()
    {
        return static::ONCE_PER_ITEM;
    }


    /**
     * Returns 'true' if execution of this task is to be skipped.
     *
     * Used for optional tasks.
     *
     * @see $skip
     */
    public function skip()
    {
        return $this->skip;
    }


    /**
     * Use this to mark this task as "to be skipped".
     */
    public function skipIt()
    {
        $this->skip = true;
        $this->setStatusSkipped();
        return true;
    }


    /**
     * Provide an array with item subfolders and CIFolder objects
     * already initialized for each one.
     *
     * This is useful/necessary for self-recursive tasks, in order to
     * e.g. access configuration of certain item subfolders, etc.
     */
    public function setItemSubDirs($itemSubDirs)
    {
        if (!is_array($itemSubDirs)) throw new Exception(_("Invalid item subdirs: not an array."));
        // TODO: More checks if contents of array are proper?
        $this->itemSubDirs = $itemSubDirs;
    }


    /**
     * @name Common task functions
     */
    //@{

    /**
     * Load settings from config that are relevant for this task.
     *
     * @retval boolean
     *  True if everything went fine. False if not.
     */
    protected function loadSettings()
    {
        $l = $this->logger;
        $config = $this->config;

        $l->logDebug(_("Loading task settings..."));

        # OPTIONAL:
        $this->copyExclude = $config->get(self::CONF_COPY_EXCLUDE);
        if (!empty($this->copyExclude))
        {
            if (!$this->optionIsArray($this->copyExclude, self::CONF_COPY_EXCLUDE)) return false;
            $l->logDebug(sprintf(_("Copy exclude: %s"), implode(', ', $this->copyExclude)));
        }

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
        $l = $this->logger;

        $l->logDebug(sprintf(_("Task '%s' init."), $this->name));
        if (!$this->loadSettings())
        {
            $l->logError(sprintf(_("Failed to load settings for task '%s'."), $this->name));
            $this->setStatusError();
            return false;
        }

        // No need to go further, if this task is to be skipped:
        if ($this->skip()) return false;

        // Set common paths, like source- and target folder:
        $this->sourceFolder = $this->CIFolder->getPathname();
        $this->targetFolder = $this->CIFolder->getTargetFolder();
        $l->logDebug(sprintf(_("Target folder for '%s' resolves to: '%s'"), $this->CIFolder->getSubDir(), $this->targetFolder));

        // Initialize name of temporary target folder:
        $this->targetFolderStage = $this->resolveTargetFolderStage();
        $this->targetFolderStageRaw = $this->CIFolder->getTargetStageRaw();
        if ($this->targetFolderStage === false) return false;

        // Add placeholders so that other tasks may use them:
        $this->config->addPlaceholder(__DIR_SOURCE__, $this->sourceFolder);
        $this->config->addPlaceholder(__DIR_TARGET__, $this->targetFolder);
        $this->config->addPlaceholder(__DIR_TARGET_STAGE__, $this->targetFolderStage);
        $this->config->addPlaceholder(__DIR_BASE__, $this->CIFolder->getBaseFolder());

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
        $this->setStatusRunning();

        // Must return true on success:
        return true;
    }


    /**
     * Actions to be performed *after* run() finished successfully;
     *
     * @retval boolean
     *  True if task shall proceed. False if not.
     */
    public function finalize()
    {
        // Must return true on success:
        return true;
    }
    //@}


    /**
     * @name Querying the task status
     * These methods are related to reading/checking which status the task is currently in.
     */
    //@{

    /**
     * Return the status of processing of this task.
     * Used to determine if task is still running, already finished - and if successful or not.
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * Queries the task's status.
     *
     * @retval boolean
     *  'true' if task status is #STATUS_UNDEFINED, 'false' if not.
     */
    public function statusUndefined()
    {
        if ($this->status == self::STATUS_UNDEFINED) return true;
        return false;
    }

    /**
     * Queries the task's status.
     *
     * @retval boolean
     *  'true' if task status is #STATUS_RUNNING, 'false' if not.
     */
    public function statusRunning()
    {
        if ($this->status == self::STATUS_RUNNING) return true;
        return false;
    }

    /**
     * Queries the task's status.
     *
     * @retval boolean
     *  'true' if task status is #STATUS_WAIT, 'false' if not.
     */
    public function statusWait()
    {
        if ($this->status == self::STATUS_WAIT) return true;
        return false;
    }

    /**
     * Queries the task's status.
     *
     * @retval boolean
     *  'true' if task status is #STATUS_DONE, 'false' if not.
     */
    public function statusDone()
    {
        if ($this->status == self::STATUS_DONE) return true;
        return false;
    }

    /**
     * Queries the task's status.
     *
     * @retval boolean
     *  'true' if task status is #STATUS_PBCT, 'false' if not.
     */
    public function statusPBCT()
    {
        if ($this->status == self::STATUS_PBCT) return true;
        return false;
    }

    /**
     * Queries the task's status.
     *
     * @retval boolean
     *  'true' if task status is #STATUS_PBC, 'false' if not.
     */
    public function statusPBC()
    {
        if ($this->status == self::STATUS_PBC) return true;
        return false;
    }

    /**
     * Queries the task's status.
     *
     * @retval boolean
     *  'true' if task status is #STATUS_SKIPPED, 'false' if not.
     */
    public function statusSkipped()
    {
        return ($this->status == self::STATUS_SKIPPED) ? true : false;
    }

    /**
     * Queries the task's status.
     *
     * @retval boolean
     *  'true' if task status is #STATUS_ERROR, 'false' if not.
     */
    public function statusError()
    {
        if ($this->status == self::STATUS_ERROR) return true;
        return false;
    }

    /**
     * Queries the task's status.
     *
     * @retval boolean
     *  'true' if task status is #STATUS_CONFIG_ERROR, 'false' if not.
     */
    public function statusConfigError()
    {
        if ($this->status == self::STATUS_CONFIG_ERROR) return true;
        return false;
    }
    //@}


    /**
     * @name Setting the task status
     * These methods are related to writing/setting the task's current status.
     */
    //@{

    /**
     * Updates the task's current status to $status.
     * See CITask const definitions about valid task states.
     *
     * Returns 'true' if status is good and task shall continue,
     * and 'false' if a negative state was set, and task execution shall abort.
     *
     * @retval boolean
     *  'false' if task was set to a negative/faulty/unsuccessful status, such as #STATUS_ERROR or #STATUS_CONFIG_ERROR.
     *  'true' for all other states.
     */
    protected function setStatus($status)
    {
        // Certain states have higher priority over others, so
        // not only higher-value states can override existing one.
        if ($status > $this->status)
        {
            $this->status = $status;
        }

        if ($status == self::STATUS_ERROR) return false;
        if ($status == self::STATUS_CONFIG_ERROR) return false;
        return true;
    }


    /**
     * Set task status to #STATUS_UNDEFINED
     *
     * @see #setStatus()
     */
    protected function setStatusUndefined()
    {
        $this->setStatus(self::STATUS_UNDEFINED);
    }

    /**
     * Set task status to #STATUS_RUNNING
     *
     * @see #setStatus()
     */
    protected function setStatusRunning()
    {
        $this->setStatus(self::STATUS_RUNNING);
    }

    /**
     * Set task status to #STATUS_WAIT
     *
     * @see #setStatus()
     */
    protected function setStatusWait()
    {
        $this->setStatus(self::STATUS_WAIT);
    }

    /**
     * Set task status to #STATUS_DONE
     *
     * @see #setStatus()
     */
    protected function setStatusDone()
    {
        $this->setStatus(self::STATUS_DONE);
    }

    /**
     * Set task status to #STATUS_PBCT
     *
     * @see #setStatus()
     */
    protected function setStatusPBCT()
    {
        $this->setStatus(self::STATUS_PBCT);
    }

    /**
     * Set task status to #STATUS_PBC
     *
     * @see #setStatus()
     */
    protected function setStatusPBC()
    {
        $this->setStatus(self::STATUS_PBC);
    }

    /**
     * Set task status to #STATUS_SKIPPED
     *
     * @see #setStatus()
     */
    protected function setStatusSkipped()
    {
        $this->setStatus(self::STATUS_SKIPPED);
    }

    /**
     * Set task status to #STATUS_ERROR
     *
     * @see #setStatus()
     */
    protected function setStatusError()
    {
        $this->setStatus(self::STATUS_ERROR);
    }

    /**
     * Set task status to #STATUS_CONFIG_ERROR
     *
     * @see #setStatus()
     */
    protected function setStatusConfigError()
    {
        $this->setStatus(self::STATUS_CONFIG_ERROR);
    }

    //@}


    /**
     * Creates an instance of a Task matching classname
     * returned by #getTaskClassName().
     */
    public static function getTaskByName($taskName, $CIFolder)
    {
        if (empty($taskName))
        {
            throw new Exception(_("Cannot create task: No taskname given."));
        }

        $className = self::getTaskClassName($taskName);

        if (empty($className))
        {
            throw new Exception(sprintf(_("Cannot create object: Invalid task name '%s'."), $taskName));
        }

        if (!class_exists($className))
        {
            throw new Exception(sprintf(_("Cannot create object: Cannot find class '%s'."), $className));
        }

        $task = new $className($CIFolder);

        // TODO: Sanity checks if class valid, etc.
        return $task;
    }


    /**
     * Returns the class name of a task corresponding to "$taskName".
     *
     * Naming convention is that all task classes start with the word "Task".
     */
    public static function getTaskClassName($taskName)
    {
        if (empty($taskName))
        {
            return false;
        }
        #FIXME: Task classes are currently not loaded since switching to PHP namespaces.
        #Add namespace to className?
        $className = __NAMESPACE__."\\Task" . $taskName;

        return $className;
    }


    /**
     * @name Temp folder handling.
     */
    //@{

    /**
     * Tests if the currently set temp-folder of this task's CIFolder exists, and
     * is writeable.
     *
     * @retval boolean
     *  Returns 'true' if temp folder exists and meets all conditions.
     *  If not, then exceptions are thrown.
     *
     * @see $tempFolder
     */
    protected function checkTempFolder()
    {
        $l = $this->logger;

        $tempFolder = $this->getTempFolder();
        if ($tempFolder === false)
        {
            throw new Exception(sprintf(_("%s: Temp folder is not set or invalid."), $this->getName()));
        }

        if (is_dir($tempFolder))
        {
            $l->logInfo(sprintf(_("Task '%s': temp folder is '%s'."), $this->getName(), $tempFolder));
        }

        if (!is_writeable($tempFolder))
        {
            throw new Exception(sprintf(_("%s: Temp folder '%s' is not writable by user '%s'. Check permissions?"), $this->getName(), Helper::getPhpUser(), $tempFolder));
        }

        return true;
    }


    /**
     * Returns the current temp folder to use by this task.
     * Please run #checkTempFolder() before calling this to make sure we have a valid tempFolder.
     *
     * @retval string
     *  Path of the temp folder for this task.
     *
     * @see $tempFolder
     */
    protected function getTempFolder()
    {
        return $this->tempFolder;
    }


    /**
     * Sets the temp folder of this task.
     *
     * @see $tempFolder
     */
    protected function setTempFolder($tempFolder=null)
    {
        if (is_null($tempFolder))
        {
            // If none given, use default:
            $tempFolder = $this->CIFolder->getTempFolder();
        }

        $this->tempFolder = $tempFolder;
        $this->config->addPlaceholder(__DIR_TEMP__, $this->tempFolder);

        return true;
    }
    //@}


    /**
     * Checks if a file shall be excluded from processing,
     * according to file patterns in $excludePatterns.
     *
     * @retval boolean
     *  Returns 'true' if file shall be excluded, 'false' if it shall be copied.
     */
    protected function exclude($file, $excludePatterns)
    {
        // If no excludes are set, no files shall be excluded:
        if (empty($excludePatterns)) return false;

        if (!is_array($excludePatterns))
        {
            throw new \InvalidArgumentException(sprintf(_("Parameter 'excludePatterns' must be an array, but is: %s"), gettype($excludePatterns)));
        }

        if (empty($file))
        {
            throw new \InvalidArgumentException(_("Filename must not be empty"));
        }

        $exclude = false;
        foreach ($excludePatterns as $pattern)
        {
            $match = fnmatch($pattern, basename($file));
            if ($match) $exclude = $pattern;
        }

        return $exclude;
    }


    /**
     * Checks if config option is an array and sets task status
     * to #STATUS_CONFIG_ERROR if not.
     */
    protected function optionIsArray($option, $optionName)
    {
        $l = $this->logger;

        if (is_array($option)) return true;

        $l->logError(sprintf(
                    _("Invalid configuration '%s': Must be an array. Maybe missing '[]'?"),
                    $optionName));
        $this->setStatusConfigError();
        return false;
    }


    /**
     * Constructs the name of the temporary target staging folder for this CIFolder.
     * Returns the resolved $targetFolderStage as string, or False if problems occurred.
     *
     * @see static::CONF_TARGET_STAGE
     * @see $this->targetFolderStage
     */
    protected function resolveTargetFolderStage($CIFolder=null)
    {
        $l = $this->logger;

        if (is_null($CIFolder)) $CIFolder = $this->CIFolder;
        $targetFolderStage = $CIFolder->getTargetFolder($staging=true);

        if (empty($targetFolderStage))
        {
            $l->logError(sprintf(
                        _("Target folder (staging) for '%s' could not be resolved properly."),
                        $this->CIFolder->getSubDir()));
            return false;
        }

        $l->logDebug(sprintf(
                    _("Target folder (staging) for '%s' resolves to: '%s'"),
                    $this->CIFolder->getSubDir(),
                    $targetFolderStage));

        return $targetFolderStage;
    }

}

?>
