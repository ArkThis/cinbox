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

use \ArkThis\Helper;
use \ArkThis\CInbox\CIFolder;
use \ArkThis\CInbox\CIItem;
use \ArkThis\CInbox\CIConfig;
use \ArkThis\CInbox\Task\CITask;
use \Exception as Exception;

// TODO: Create some some object/variable to share common
// settings/data/information between subdir tasks.
// Currently each task is fairly unable to get access to outcomes/info from
// other tasks. This works, but it could be improved :)

/**
 * This class is a logical representation of an CInbox Item.
 * Each Item represents an archival package to regard as one.
 *
 * Therefore, each Item requires a unique identifier (Item ID), which
 * is also known in archival terms as "object identifier", "archive signature" or similar.
 *
 * The list of tasks to be processed for each Item is handled in here.
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
class CIItem extends CIFolder
{

    /* ========================================
     * CONSTANTS
     * ======================================= */

    /**
     * @name Config options
     */
    //@{
    const CONF_SECTION_ITEM = '__INBOX__';                  // Global Item settings are stored in Inbox-section, too.

    const CONF_MAX_FOLDER_DEPTH = 'MAX_FOLDER_DEPTH';       // Max. folder recursion level.
    const CONF_ITEM_ID_VALID = 'ITEM_ID_VALID';             // RegEx to validate item ID
    const CONF_BUCKET_SCRIPT = 'BUCKET_SCRIPT';             // Bucket script. See: callBucketScript().
    const CONF_COOLOFF_TIME = 'COOLOFF_TIME';               // Time to wait until considering an item "old (=unchanging) enough" to be processed (in minutes)
    const CONF_COOLOFF_FILTERS = 'COOLOFF_FILTERS';         // Filter to exclude some stat() values from cooloff-check
    const CONF_TASKLIST = 'TASKLIST';                       // List of tasks to process (task name = class name of Task)

    const CONF_TOKEN_DONE = 'TOKEN_DONE';                   // Filename to create when an item was successful and moved to DIR_DONE.
    const CONF_TOKEN_ERROR = 'TOKEN_ERROR';                 // Filename to create when an item had a problem and was moved to DIR_ERROR.
    //@}

    /**
     * @name Item states
     */
    //@{
    const STATUS_TODO = 'TODO';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_DONE = 'DONE';
    const STATUS_ERROR = 'ERROR';

    public static $itemStates = array(            // An array of string with all possible item states.
            self::STATUS_TODO,
            self::STATUS_IN_PROGRESS,
            self::STATUS_DONE,
            self::STATUS_ERROR,
            );
    //@}

    /**
     * @name Miscellaneous
     */
    //@{
    const CHANGELOG_FILE = 'item_changelog.txt';            // Textfile to monitor folder changes in.
    const MEMORY_TS_FORMAT = 'Ymd-His';                     // String format for timestamp value in Item->memory entries (@see: self::recallNice())
    //@}



    /* ========================================
     * PROPERTIES
     * ======================================= */

    protected $itemId;                            // Item "object identifier" = "archive signature"
    protected $initialized = false;               // True if initialized. False if not.
    protected $configured = false;                // True if config has been loaded. False if not.

    public $tryAgain = false;                    // True if item needs to be reset back to #STATUS_TODO (@see CITask::STATUS_WAIT)

    protected $memory = array();                  // A dictionary to hold information to be shared across tasks for this item.
    protected $tokens = array();                  // A number of "token" files to create, depending on processing status of item.


    /* ========================================
     * METHODS
     * ======================================= */

    function __construct($itemId, &$logger, $folderName, $baseFolder=null)
    {
        parent::__construct($logger, $folderName, $baseFolder);

        $this->setItemId($itemId);
        $logger->logHeader(sprintf(
            _("Item: %s"),
            $itemId
        ));
    }



    public function isInitialized()
    {
        return $this->initialized;
    }


    public function isConfigured()
    {
        return $this->configured;
    }


    /**
     * Sets the current status of this Item to $status.
     *
     * @see $itemStates
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }


    /**
     * Returns the current status of this Item.
     *
     * @see $itemStates
     */
    public function getStatus()
    {
        return $this->status;
    }


    public function setItemId($itemId)
    {
        $this->itemId = $itemId;
    }


    public function getItemId()
    {
        return $this->itemId;
    }


    /**
     * Add information (key/value) to the item's "memory" property.
     *
     * @param[in] key       The key in the memory array to store the value in.
     * @param[in] value     The value to "remember".
     * @param[in] append    True:Append $value as last item in array; False: Overwrite memory[$key]=$value.
     * @return True on success.
     */
    public function remember($key, $value, $append=false)
    {
        $l = $this->logger;

        $l->logDebug(sprintf(
            _("Item memory '%s': Remembering '%s' as '%s'.\n"),
            $this->itemId,
            $key,
            print_r($value, true)
        ));

        $is_new = !array_key_exists($key, $this->memory);

        // Initialize empty array if key is new (or to-be-replaced):
        if ($is_new or !$append)
        {
            $this->memory[$key] = array();
        }

        // Use the current UNIX timestamp as sub-key.
        // This allows to know *when* the information was stored here.
        $time = time();

        // To avoid collisions on the same second, bump it up "1s later".
        // - until we get a free space. Yes, it's a clever hack :P
        while (array_key_exists($time, $this->memory[$key]))
        {
            $time += 1;
        }

        $this->memory[$key][$time] = $value;

        return true;
    }


    /**
     * Recall information (value by key) from the item's "memory" property.
     *
     * @param[in] key       The key in the memory array to look up.
     * @param[in] strict    True: throw exception if key is not found, otherwise just return "null";
     * @param[in] unique    True: only return non-duplicate values for the given key.
     * @return the uninterpreted value stored under the given $key.
     */
    public function recall($key, $strict=true, $unique=false)
    {
        $l = $this->logger;

        if (!array_key_exists($key, $this->memory))
        {
            if ($strict)
            {
                throw new LogicException(sprintf(
                    _("Item memory '%s': Unable to recall key '%s', because it's not set."),
                    $this->itemId,
                    $key
                ));
            }

            // Return null if key doesn't exist, but we don't care:
            $value = null;
        }
        else // array key *does* exist:
        {
            if ($unique) {
                // Throw out duplicate values:
                $value = array_unique($this->memory[$key]);
            } else {
                $value = $this->memory[$key];
            }
        }

        $l->logDebug(sprintf(
            _("Item '%s': Recalling '%s' as '%s'.\n"),
            $this->itemId,
            $key,
            print_r($value, true)
        ));

        return $value;
    }


    /**
     * Same as recall(), but formats the stored data in a more human-readable
     * way. Translating the unix time to a formatted date/time string, and
     * reformatting the information in a more "common" way.
     */
    public function recallNice($key, $strict=true, $unique=false)
    {
        $l = $this->logger;

        // Recall entry (array) with unix-time keys:
        $entry = $this->recall($key, $strict, $unique);
        $newEntries = array();   // Target array with "$new_key" format.

        if (empty($entry) or !is_array($entry))
        {
            $l->logInfo(sprintf(
                _("Recalled empty key (%s) from CIItem memory. Strict = %d"),
                $key,
                $strict
            ));

            // This will be an empty array:
            return $newEntries;
        }

        foreach ($entry as $unixTime => $value)
        {
            $timestamp = date(self::MEMORY_TS_FORMAT, $unixTime);

            $newEntry = array();   // This is going out.
            // Populate it properly:
            $newEntry['timestamp'] = $timestamp;
            $newEntry['unixTime'] = $unixTime;
            $newEntry['value'] = $value;

            $newEntries[] = $newEntry;
        }

        return $newEntries;
    }


    /**
     * Delete an information entry (by key) from the item's "memory" property.
     *
     * @param[in] key   The key in the memory array to delete.
     * @return True if successful, False if not.
     */
    public function forget($key)
    {
        $l = $this->logger;

        if (empty($key))
        {
            throw new InvalidArgumentException(sprintf(
                _("Item memory '%s': Cannot forget empty key value. 🤔️"),
                $this->itemId
            ));
        }

        if (!isset($this->memory[$key]))
        {
            $l->logDebug(sprintf(
                _("Item memory '%s': Asked to forget a key that wasn't remembered: '%s'?"),
                $this->itemId,
                $key
            ));
            // Maybe it's an error to believe the key was there, but this is outside of my scope here,
            // so I say it's okay. (like "delete a file that's gone": okay. it's gone already.)
            return true;
        }

        // This line *actually* removes the key/value:
        unset($this->memory[$key]);

        // Success if key is gone, false if "unset" didn't work as expected:
        return !isset($this->memory[$key]);
    }


    /**
     * Checks if itemId is valid, according to Regular Expressions
     * in config file: #ITEM_ID_VALID
     *
     * @param string itemId              The itemId (=object identifier = archive signature)
     * @param string itemIdValidRegEx    Array of RegEx (string) from config file: #ITEM_ID_VALID
     * @return True if valid, False if not.
     */
    public function isItemIdValid($itemId, $itemIdValidRegEx)
    {
        $l = $this->logger;

        // No Regexp means no validation. Means: Every ID is fine.
        if (empty($itemIdValidRegEx)) return true;

        if (empty($itemId))
        {
            throw new Exception(_("Unable to validate ID: Item ID is empty."));
        }

        if (!is_array($itemIdValidRegEx))
        {
            throw new \InvalidArgumentException(sprintf(
                        _("Invalid configuration '%s': Must be an array. Maybe missing '[]'?"),
                        self::CONF_ITEM_ID_VALID));
        }

        foreach ($itemIdValidRegEx as $index=>$regEx)
        {
            $result = preg_match($regEx, $itemId, $match);
            if ($result === false)
            {
                throw new Exception(sprintf(
                            _("preg_match failed for item ID '%s' and Regular Expression '%s'."),
                            $itemId,
                            $regEx));
            }
            elseif ($result == 1)
            {
                // We have a winner! :D
                $l->logDebug(sprintf(_("Item ID matches RegEx #%d: %s"), $index+1, $regEx));
                return true;
            }
        }

        // If no success before this line, then ItemID is not valid:
        return false;
    }


    /**
     * Load item configuration from INI file.
     * It makes sure that placeholders are resolved and
     * default values are loaded.
     *
     * It sets the property $configured to true, to indicate
     * that the item has been configured properly.
     *
     * @retval boolean
     *  'true' if successful, 'false' if not.
     */
    public function initItemSettings()
    {
        $l = $this->logger;
        $itemId = $this->itemId;

        if (empty($itemId))
        {
            throw new Exception(_("Unable to init item: Item ID empty."));
        }

        $config = $this->config;

        // This clears all previously set placeholders and
        // is setting the current timestamp values for *the whole item run":
        $config->initPlaceholders(
            $config->getPlaceholdersTime()
        );

        $config->addPlaceholder(__ITEM_ID__, $itemId);                  // Add current item id as placeholder value.
        $config->addPlaceholder(__ITEM_ID_UC__, strtoupper($itemId));   // Uppercase
        $config->addPlaceholder(__ITEM_ID_LC__, strtolower($itemId));   // Lowercase

        $configArray = $config->getConfigForSection(self::CONF_SECTION_ITEM);

        // Init Item config (not subfolders, just item):
        $this->config->setSettingsDefaults($this->getDefaultValues());
        $this->config->loadSettings($configArray);

        // At this point, we can pick up the resolved token names:
        $this->tokens[self::STATUS_DONE] = $this->config->get(self::CONF_TOKEN_DONE);
        $this->tokens[self::STATUS_ERROR] = $this->config->get(self::CONF_TOKEN_ERROR);

        $l->logDebug(sprintf(
            _("Item Token filenames: %s"),
            print_r($this->tokens, true)
        ));

        // ----------------------
        $this->configured = true;
        return true;
    }


    /**
     * Initialize an Item, so that everything's ready to begin
     * processing the tasklist.
     *
     * NOTE: initItemSettings() must have been run before!
     *
     * The ItemID is checked against CONF_ITEM_ID_VALID.
     * The bucket script is executed.
     */
    public function initItem()
    {
        $l = $this->logger;
        $config = $this->config;
        $itemId = $this->itemId;

        if (!$this->configured)
        {
            throw new Exception("Item not configured. initItemSettings() has to be run first.");
        }

        // Validate Item ID:
        $itemIdValidRegEx = $config->get(self::CONF_ITEM_ID_VALID);
        if ($this->isItemIdValid($itemId, $itemIdValidRegEx))
        {
            $l->logMsg(sprintf(_("Item ID '%s' is valid. Good!"), $itemId));
            $l->logNewline();
        }
        else // invalid item ID:
        {
            throw new Exception(sprintf(
                        _("Invalid Item ID: '%s' does not match any of the RegEx patterns in %s"),
                        $itemId,
                        self::CONF_ITEM_ID_VALID));
        }

        // Calculate __BUCKET__ value:
        $bucket = null;
        $result = $this->callBucketScript($config->get(self::CONF_BUCKET_SCRIPT), $bucket);
        if ($result !== true && $result > CIExec::EC_OK)
        {
            throw new Exception(sprintf(_("Bucket script returned error code %d."), $result));
        }
        if ($result === true && empty($bucket))
        {
            throw new Exception(sprintf(_("Bucket script returned empty bucket."), $result));
        }
        $config->addPlaceholder(__BUCKET__, $bucket);

        $this->initialized = true;
        return true;
    }


    /**
     * This calls an external script with the ItemID as argument.
     *
     * The output of that script will be used as-is and resolves the placeholder "[@BUCKET@]".
     * This can be used to have a custom subfolder structure for each Item
     * at the target destination.
     *
     * For example:
     * ItemID = VX-00815
     * Bucket = VX/00/VX-00815
     *
     * As you can see in the example, the resulting subfolder structure creates
     * something like "buckets" which divides the amount of ItemIDs over several subfolders,
     * following a custom syntax (which would be in the external script).
     */
    // TODO: Don't hardcode the arguments provided to the bucket script, but
    //       rather let the user decide in the configfile which values to pass.
    //       (just like pre/postproc scripts)
    public function callBucketScript($script, &$bucket)
    {
        $l = $this->logger;

        if (empty($script))
        {
            $l->logDebug(sprintf(
                _("No bucket script given. NOTE: Placeholder '%s' will NOT be resolved."),
                __BUCKET__
            ));
            return false;
        }

        if (!file_exists($script))
        {
            throw new Exception(sprintf(
                        _("Bucket script not found: '%s'"),
                        $script));
        }

        // Provide ItemID and Item's path to bucket script:
        $command = sprintf("%s %s '%s'",
                $script,
                $this->itemId,
                $this->getPathname());

        $l->logInfo(sprintf(
                    _("Calling bucket script '%s' with item ID '%s'..."),
                    $script,
                    $this->itemId,
                    $this->getPathname()));

        $exec = new \ArkThis\CInbox\CIExec();
        $exitCode = $exec->execute2($command);

        if ($exitCode != CIExec::EC_OK) return $exitCode;

        $result = $exec->getLastOutput();
        $bucket = $result[0]; // Only return *1st* line of output from script!
        $l->logMsg(sprintf(_("Bucket script returned '%s'."), $bucket));
        return true;
    }


    /**
     * Set default values for Item settings.
     * This must be loaded before 'loadSettings()' in order to have default values instead
     * of empty values where no setting was configured in config file.
     */
    public function getDefaultValues()
    {
        $defaultValues = array(
                self::CONF_ITEM_ID_VALID => null,                  // By default, itemId syntax is undefined = everything valid.
                self::CONF_BUCKET_SCRIPT => null,                  // None by default.
                self::CONF_MAX_FOLDER_DEPTH => 99,                 // Max. depth in folder recursions
                self::CONF_COOLOFF_TIME => 30,                     // in minutes.
                self::CONF_COOLOFF_FILTERS => null,                // List of stat() values to exclude from cooloff-check

                // TODO: Empty this. It is confusing and odd to have a preconfigured tasklist here.
                self::CONF_TASKLIST => array(
                    'FilesWait',                // Wait until certain files are present
                    'DirListCSV',               // Create directory listing as CSV
                    'CleanFilenames',           // Make sure that file/foldernames are clean
                    'PreProcs',                 // Start pre-processing scripts
                    'FilesValid',               // Check if files are valid, according to config
                    'FilesMustExist',           // Check if files are present, according to config
                    'HashGenerate',             // Generate Hashcodes for all files
                    'HashSearch',               // Find already existing hashcodes in the source
                    'CopyToTarget',             // Copy files to their respective target folders
                    'HashValidate',             // Verify target copy against hashcodes in tempfolder
                    'RenameTarget',             // Move temp target files to final position.
                    'HashOutput',               // Write hashcodes to target in selected format
                    'PostProcs',                // Same as PreProcs, but on the target
                    'LogfileCopy',              // Copy-and-rename logfile to a separate location
                    ),
                );

        return $defaultValues;
    }


    /**
     * Recursion through subfolder structure of current item.
     * Returns a flat array with:
     *      keys:    foldernames (absolute)
     *      values:  CIFolder (initialized with config and parent set)
     */
    protected function initItemSubFolders($folderName, $depth=null, $parent=null)
    {
        $l = $this->logger;

        // Configuration object must be loaded and initialized here already.
        $itemConfig = $this->config;

        $maxDepth = $itemConfig->get(self::CONF_MAX_FOLDER_DEPTH);
        if ($depth > $maxDepth)
        {
            throw new Exception(sprintf(
                _("Maximum recursion depth %d exceeded (%d)!"),
                $maxDepth, $depth
            ));
        }

        // Include start folder (baseFolder is itemFolder):
        $CIFolder = $this->initItemSubFolder($folderName, $this->getPathname(), $parent);
        $result = array($folderName => $CIFolder);

        $all = glob($folderName . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        asort($all);

        foreach($all as $entry)
        {
            $result = array_merge($result, self::initItemSubFolders($entry, $depth +1, $CIFolder));
        }

        return $result;
    }


    protected function initItemSubFolder($folderName, $baseFolder, $parent)
    {
        $l = $this->logger;

        // Configuration object must be loaded and initialized here already.
        $itemConfig = $this->config;

        // TODO Idea:
        // Check read/write access to all subfolders here?
        // Throw Exception if not.

        //----------------.
        $folder = new \ArkThis\CInbox\CIFolder($l, $folderName, $baseFolder);
        // FIXME: Why is $folderName provided as function parameter when it's
        //        not read before it's overwritten/initialized here?
        $folderName = $folder->getPathname();
        $folder->setParentItem($this);
        $folder->setParentFolder($parent);
        $folder->setTempFolder($this->getTempFolder());
        $l->logDebug(sprintf(
            _("Initializing Item subfolder: '%s' (=%s) / base: '%s'"),
            $folderName,
            $folder->getSubDir(),
            $baseFolder
        ));

        // Set as many placeholder values that we can already provide at this point:
        $itemConfig->addPlaceholder(__DIR_BASE__, $baseFolder);
        $itemConfig->addPlaceholder(__DIR_SOURCE__, $folderName);

        // Copy configuration contents (!), but *not* the config-object from item to folder:
        // IMPORTANT: We use the *resolved* config from Item, so that common placeholders are not resolved twice.
        $folderConfig = $folder->getConfig();
        $folderConfig->loadConfigFromString($itemConfig->getConfigResolved());
        // TODO: Sanity checks of config values (is_array? is_numeric? etc).

        // Load and apply configuration for the current folder:
        // initPlaceholders() should have already been called for the Item, to have common timestamps per Item.
        $myConfig = $folder->getConfigForFolder();
        $folderConfig->loadSettings($myConfig);
        //-----------------

        return $folder;
    }


    /**
     * Instantiates and runs the task defined by $taskName on
     * the given $folder.
     * $folder must be an object of class type "CIFolder" and must already be
     * initialized properly before.
     *
     * $subDirs must be supplied with the output of "initItemSubFolders()".
     *
     * The return value is the task instance itself, which allows the calling function
     * to access all information to decide how to proceed.
     * See "CITask" for details about Task statuses.
     */
    public function runTask($folder, $taskName, $itemSubDirs)
    {
        $l = $this->logger;

        // Instantiate task to execute:
        $task = CITask::getTaskByName($taskName, $folder);
        // Provide the task a handle to *this* item, for common information exchange between tasks:
        $task->setParentItem($this);
        $task->setItemSubDirs($itemSubDirs);
        // TODO: Here a task may be given a handle to some object/variable to
        // share common settings/data/information between subdir tasks.
        $folderName = $folder->getPathname();

        $l->logInfo(
            sprintf(_("Running task '%s' on folder: '%s'"),
            $taskName, $folder->getSubDir()
        ));

        // Here's the call to the actual processing of each folder entry:
        if (!$task->init()) return $task;
        if (!$task->run()) return $task;
        if (!$task->finalize()) return $task;

        return $task;
    }


    /*
     *  Execute the steps required for an item to be processed.
     */
    public function process()
    {
        $l = $this->logger;

        $itemId = $this->itemId;
        $config = $this->config;
        $errors1 = 0;       // Regular errors
        $errors2 = 0;       // Errors inside task, but execution may (partially continue)

        // Make sure there are no leftovers:
        $this->tryAgain = false;

        // Check if item has been initialized before processing it. Exception if not.
        if (!$this->isInitialized()) throw
            new Exception(sprintf(
                _("Unable to process item '%s'. Item not initialized."),
                $itemId
            ));

        $l->logMsg(sprintf(_("Processing item '%s'..."), $itemId));

        // baseFolder is the Item path, but we also start at the Item's path :)
        $itemFolder = $this->getPathname();

        // Store a list of subfolders - as they are NOW.
        // This is used later on to compare if folders have changed between tasks.
        $subDirsList = Helper::getRecursiveFolderListing($itemFolder, GLOB_ONLYDIR);
        ksort($subDirsList);

        // Item subfolders can be changed by tasks. Will be reloaded if necessary (see $subDirsNow, below).
        $subDirs = $this->initItemSubFolders($itemFolder);
        $l->logInfo(sprintf(
            _("Subfolder structure of '%s':\n%s"),
            $itemFolder,
            print_r(array_keys($subDirs), true)
        ));

        $tasksDone = 0;
        $taskList = $config->get(self::CONF_TASKLIST);
        $tasksError = array();                          // Logs erroneous tasks
        foreach ($taskList as $taskIndex=>$taskName)
        {
            $task_errors1 = 0;
            $task_errors2 = 0;
            $abortAfterTask = false;

            // Update item subfolders if necessary:
            $subDirsNow = Helper::getRecursiveFolderListing($itemFolder, GLOB_ONLYDIR);
            ksort($subDirsNow);
            $diff = Helper::arrayDiffKey($subDirsList, $subDirsNow);
            if (!empty($diff))
            {
                $l->logMsg(sprintf(
                    _("(%s) Subfolder structure has changed. Loading it again."),
                    $itemId
                ));

                $subDirs = $this->initItemSubFolders($itemFolder);
                $l->logInfo(sprintf(
                    _("Subfolder structure of '%s':\n%s"),
                    $itemFolder,
                    print_r(array_keys($subDirs), true)
                ));
            }

            $l->logNewline();
            $l->logMsg($l->getHeadline());
            $l->logMsg(sprintf(_("Executing task #%d: '%s'..."), $taskIndex +1, $taskName));
            $l->logMsg($l->getHeadline());

            foreach ($subDirs as $subDir=>$CIFolder)
            {
                $task = $this->runTask($CIFolder, $taskName, $subDirs);
                // TODO: Add some object to group subdir-tasks and share common
                // settings/data/information between subdir tasks.

                // TODO (issue #23):
                // If item had an error, and TARGET_STAGE was already populated: delete it.
                // Currently it's confusing where, in which class (CIFolder vs
                // CITask) resolves and keeps/tracks the targetStage path.

                // Evaluate task's status to see if we shall abort or "problem but continue".
                if ($task->statusError())
                {
                    $l->logError(sprintf(
                        _("(%s): Issue found in task '%s'. Aborting task list"),
                        $itemId, $taskName
                    ));
                    $abortAfterTask = true;
                    $task_errors1++;
                    break;
                }
                if ($task->statusConfigError())
                {
                    $l->logError(sprintf(
                        _("(%s): Problem with config setting found in task '%s'. Aborting task list"),
                        $itemId, $taskName
                    ));
                    $abortAfterTask = true;
                    $task_errors1++;
                    break;
                }
                elseif ($task->statusPBCT())
                {
                    $l->logWarning(sprintf(
                        _("(%s): Issue found in task '%s'. This Task may proceed, but tasklist will be aborted."),
                        $itemId, $taskName
                    ));
                    $abortAfterTask = true;
                    $task_errors2++;
                }
                elseif ($task->statusPBC())
                {
                    $l->logWarning(sprintf(
                        _("(%s): Issue found in task '%s'. Processing may still proceed..."),
                        $itemId, $taskName
                    ));
                    $task_errors2++;
                }
                elseif ($task->statusDone())
                {
                    $l->logDebug(sprintf(
                        _("(%s): Task '%s' okay for '%s'."),
                        $itemId, $taskName, $CIFolder->getSubDir()
                    ));
                }
                elseif ($task->statusSkipped())
                {
                    $l->logInfo(sprintf(
                        _("(%s): Task '%s' skipped for '%s'."),
                        $itemId, $taskName, $CIFolder->getSubDir()
                    ));
                }
                elseif ($task->statusWait())
                {
                    $l->logMsg(sprintf(
                        _("(%s): Task '%s' triggered Item to wait. Resetting Item after this task."),
                        $itemId, $taskName
                    ));
                    $this->tryAgain = true;
                    $abortAfterTask = true;
                    break;
                }
                else
                {
                    $l->logError(sprintf(
                        _("(%s): Task '%s': Invalid task status '%s'."),
                        $itemId, $taskName, $task->getStatus()
                    ));
                    $task_errors1++;
                    break;
                }


                // Special task types may abort earlier:
                if ($task->isRecursive())
                {
                    $l->logMsg(sprintf(
                        _("Task '%s' is recursive itself. Applied only to '%s'."),
                        $taskName, $CIFolder->getSubDir()
                    ));
                    break;
                }
                elseif($task->oncePerItem())
                {
                    $l->logMsg(sprintf(
                        _("Task '%s' is marked as 'once per Item'. Applied only to '%s'."),
                        $taskName, $CIFolder->getSubDir()
                    ));
                    break;
                }

                $parent = $CIFolder;
            }

            if ($task_errors1 + $task_errors2 == 0)
            {
                $l->logMsg(sprintf(
                    _("(%s): Task '%s' ran successfully."),
                    $itemId, $taskName
                ));
                $tasksDone++;
            }
            else
            {
                $tasksError[] = $taskName;
                $l->logError(sprintf(_("(%s): Task '%s' ran with %d errors."),
                            $itemId,
                            $taskName,
                            $task_errors1 + $task_errors2
                            ));
            }

            $errors1 += $task_errors1;
            $errors2 += $task_errors2;

            if ($abortAfterTask) break;
        }


        if ($errors1 + $errors2 > 0)
        {
            $l->logNewline(2);
            $l->logError(sprintf(
                        _("Item '%s': %d/%d task(s) processed, but with %d errors. Please check the logs!"),
                        $itemId,
                        $taskIndex+1,
                        count($taskList),
                        $errors1 + $errors2));

            $l->logError(sprintf(
                        _("%d task(s) had errors: %s"),
                        count($tasksError),
                        implode(', ', $tasksError)
                        ));
        }

        $success = ($errors1 + $errors2 > 0) ? false : true;
        if ($success)
        {
            $l->logNewline(2);
            $l->logMsg(sprintf(
                        _("Item '%s': %d/%d task(s) processed successfully!"),
                        $itemId,
                        $tasksDone,
                        count($taskList)));
        }

        return $success;
    }


    /**
     * Do whatever things are to-be-done to finalize an item
     * in order to proceed to the next one.
     */
    public function finalize()
    {
        $l = $this->logger;

        $l->logDebug(sprintf(
            _("Item memory (%s) on finalize: %s\n"),
            $this->itemId,
            print_r($this->memory, true)
        ));

        // TODO more?
        return true;
    }


    /**
     * Returns 'true' if start condition for this item is fulfilled.
     * False if item is not ready for processing yet.
     */
    public function canStart()
    {
        $l = $this->logger;

        $l->logMsg(sprintf(
            _("Checking if item '%s' is ready for processing..."),
            $this->itemId
        ));

        // TODO: This would be a good point to check if item is empty - and decide whether to process it then or not.

        // TODO: Move these checks to a new function that validates settings after loading config:
        $cooloff_time = $this->config->get(self::CONF_COOLOFF_TIME);
        $cooloff_filters = $this->config->get(self::CONF_COOLOFF_FILTERS);
        if (!empty($cooloff_filters) && !is_array($cooloff_filters))
        {
            $l->logError(sprintf(
                        _("Invalid configuration '%s': Must be an array. Maybe missing '[]'?"),
                        self::CONF_COOLOFF_FILTERS));
            return false;
        }

        $age = round($this->lastChanged($cooloff_filters));
        $l->logMsg(sprintf(
            _("Item age is: %d minutes (cooloff time: %d)"),
            $age, $cooloff_time
        ));

        if ($age < $cooloff_time)
        {
            $l->logMsg(sprintf(
                _("Item not ready for processing: Need to wait %d more minutes."),
                ($cooloff_time - $age)
            ));
            return false;
        }

        return true;
    }


    public function setTempFolder($tempFolder)
    {
        $itemId = $this->itemId;
        if (empty($itemId))
        {
            throw new Exception(_("Cannot set temp folder for Item: Must set ItemID first."));
        }

        if (!is_writeable($tempFolder))
        {
            throw new Exception(sprintf(
                _("Invalid temp folder given. '%s' is not writable. Check access rights?"),
                $tempFolder
            ));
        }

        // Add itemID (lowercase) as subfolder to "tempFolder":
        // NOTE: itemID is retrieved from foldername at Inbox-level. Therefore itemID cannot contain chars invalid for dirname anymore ;)
        $tempFolder = $tempFolder . DIRECTORY_SEPARATOR . strtolower($itemId);

        if (!file_exists($tempFolder) && !mkdir($tempFolder))
        {
            throw new Exception(sprintf(
                _("Creating temp folder '%s' failed."),
                $tempFolder
            ));
        }

        return parent::setTempFolder($tempFolder);

        $l->logMsg(sprintf(
            _("Set temp folder for '%s' to '%s'."),
            $itemId, $tempFolder
        ));
    }


    /**
     * Deletes the item's temporary folder (including subfolders and files)
     */
    public function removeTempFolder()
    {
        $l = $this->logger;
        $itemId = $this->itemId;
        $tempFolder = $this->tempFolder;

        // If it's already gone, why bother? ;)
        if (!file_exists($tempFolder)) return true;

        $l->logInfo(sprintf(
            _("Trying to remove temp folder '%s'..."),
            $tempFolder
        ));

        if (Helper::removeFolder($tempFolder))
        {
            $l->logInfo(_("Removed temp folder."));
            return true;
        }
        else
        {
            $l->logError(sprintf(
                _("Could not remove temp folder '%s'."),
                $tempFolder
            ));
            return false;
        }
    }


    /**
     * Returns the "age" of an item.
     *
     * Every change to a file/folder in the item resets the age.
     * Can be used to determine if the item is still being written/changed
     * or if it is safe to process it.
     *
     * If the item's folder is empty, the modification time of the
     * folder itself will be used.
     *
     * @retval numeric
     *  Age of the youngest file/folder in $folder. In minutes.
     *
     * @see Helper::getYoungest()
     */
    public function getAge()
    {
        $youngest = Helper::getYoungest($this->getPathname());
        if (!is_a($youngest, 'SplFileInfo'))
        {
            throw new Exception(sprintf(
                _("Unable to determine age of %s. Invalid return type: %s"),
                print_r($youngest, true),
                gettype($youngest)
            ));
        }

        $age = time() - $youngest->getMTime();
        $age /= 60;     // Age in minutes
        return $age;
    }


    /**
     * Used to determine the "cooloff" status of an item.
     * It uses a temporary directory listing of the item's files/folders
     * that it compares the current file/folder state.
     *
     * The listing is created using Helper::dirlistToText().
     *
     * @param $filter [Array]  Optional list of associative keys of stat() values to exclude from comparison.
     *                         $filter is forwarded as-is to Helper::dirlistToText() (as parameter $filter_keys).
     *
     * @return float
     * @retval $age  The duration since the last change (in minutes).
     */
    public function lastChanged($filter=null)
    {
        $l = $this->logger;
        $age = 0;       // Init to avoid undefined variable (0=modified now)

        // All these variables are compared later on, so they need values:
        $list_old = '';
        $list_new = '';

        $itemFolder = $this->getPathname();
        $changelog = $this->getChangelogFile();

        if (!Helper::isFolderEmpty($itemFolder))
        {
            $list_new = Helper::dirlistToText(Helper::getRecursiveFolderListing($itemFolder), $filter);
        }

        if (file_exists($changelog))
        {
            $list_old = file_get_contents($changelog);
            $mtime = filemtime($changelog);
            $age = time() - $mtime;
            $age /= 60;         // in minutes
        }

        $diff = abs(strcmp($list_old, $list_new));

        if ($diff != 0)
        {
            // If there's any change, reset the age to "modified now":
            $age = 0;

            $l->logInfo(sprintf(_("Updating changelog: %s"), $changelog));
            if (file_put_contents($changelog, $list_new) === false)
            {
                throw new Exception(sprintf(
                            _("Could not write to changelog '%s'. Check access rights?"),
                            $changelog));
            }
        }

        return $age;
    }


    /**
     * Updates the item's source timestamp to now.
     */
    public function updateTimestamp()
    {
        $l = $this->logger;

        $sourceFolder = $this->getPathname();
        $now = time();
        $l->logInfo(sprintf(
            _("Updating Item timestamp to: %s"),
            date(DATE_ATOM, $now)
        ));

        return touch($sourceFolder);
    }


    /**
     * Returns a filename (if set) for a given token-key.
     *
     * @param[in] key   Can be any CIItem STATUS (CIItem::STATUS_DONE, CIItem::STATUS_ERROR, etc)
     * @return a filename if set for this key/status, otherwise False.
     */
    public function getTokenFilename($key)
    {
        $l = $this->logger;

        if (!array_key_exists($key, $this->tokens))
        {
            $l->logDebug(sprintf(
                _("No token filename exists for given key '%s'."),
                $key
            ));
            // This is a regular case. This is not necessarily an error.
            // Therefore no exception here.
            return false;
        }

        return $this->tokens[$key];
    }


    /**
     * Returns a parse-able text that contains all desired information to be
     * written in an item's "token" file for inter-process communication.
     *
     * @param[in] flags    See: https://www.php.net/manual/en/json.constants.php
     */
    public function getTokenData($flags=null)
    {
        // TODO/FIXME:
        // There should be a different variable for this, than just dumping the
        // item's "memory" here...
        //

        $tokenData = array();
        $tokenData['itemId'] = $this->itemId;
        $tokenData['targetFolders'] = $this->recallNice(
            'targetFolders',
            $strict=false,
            $unique=true        // For the token, we don't want duplicate memories ;)
        );

        $json = json_encode($tokenData, $flags);

        return $json;
    }


    /**
     * Writes $data to $tokenFile.
     */
    public function writeTokenFile($tokenFile, $data)
    {
        if (empty($tokenFile))
        {
            throw new DomainException(
                _("Cannot write token file: Empty filename given."),
            );
        }

        // prepare all subfolders, etc before we write the contents:
        Helper::touchFile($tokenFile);

        if (file_put_contents($tokenFile, $data) === false)
        {
            throw new RuntimeException(sprintf(
                _("Could not write to token file '%s'. Check access rights?"),
                $tokenFile
            ));
        }

        return true;
    }

}

?>
