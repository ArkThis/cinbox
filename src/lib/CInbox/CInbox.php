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

use \ArkThis\Logger;
use \ArkThis\Helper;
use \ArkThis\CInbox\CIConfig;
use \ArkThis\CInbox\CIItem;
use \Exception as Exception;


/**
 * This class represents the actual CInbox.
 * It takes care of handling and processing Items, as well as certain mechanisms
 * and settings common to the CInbox.
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
class CInbox
{

    /* ========================================
     * CONSTANTS
     * ======================================= */

    /**
     * @name Config options
     */
    //@{
    const CONF_SECTION_INBOX = '__INBOX__';             ///< Name of INI section that contains settings for the whole CInbox.

    const CONF_INBOX_NAME = 'INBOX_NAME';               ///< Name for Inbox. Must only contain alphanumeric ASCII characters.
    const CONF_PAUSE_TIME = 'PAUSE_TIME';               ///< Time to pause between each item (seconds)
    const CONF_ITEMS_AT_ONCE = 'ITEMS_AT_ONCE';         ///< Process max. this number of items in one run
    const CONF_KEEP_FINISHED = 'KEEP_FINISHED';         ///< keep finished items for x days (0=delete immediately)    TODO
    const CONF_WAIT_FOR_ITEMS = 'WAIT_FOR_ITEMS';       ///< Time to wait for new items to appear (minutes)
    const CONF_MOVE_LOGFILES = 'MOVE_LOGFILES';         ///< Move logfiles along with Items
    const CONF_ITEM_LOGSTYLE = 'ITEM_LOGSTYLE';         ///< Log output style for Items. @see Logger::setOutputFormat()
    const CONF_FOLDER_TEMP = 'DIR_TEMP';                ///< Folder where temporary files are being stored (default: /var/cinbox).
    const CONF_WORK_TIMES = 'WORK_TIMES';               ///< Define when CInbox is supposed to process items (Crontab-like syntax).
    //@}

    /**
     * @name State keeping folders
     */
    //@{
    const CONF_FOLDER_STATEKEEPING = 'DIR_STATEKEEPING';///< Foldername for item-statekeeping
    const CONF_FOLDER_LOGS = 'DIR_LOGS';                ///< Foldername for logfiles
    const CONF_FOLDER_TODO = 'DIR_TODO';                ///< Foldername for items that are to be processed
    const CONF_FOLDER_IN_PROGRESS = 'DIR_IN_PROGRESS';  ///< Foldername for items that are being processed
    const CONF_FOLDER_DONE = 'DIR_DONE';                ///< Foldername for items that have been processed successfully
    const CONF_FOLDER_ERROR = 'DIR_ERROR';              ///< Foldername for items that have errors

    /** List of all processing folders and their default values */
    public static $processingFolders = array(
        self::CONF_FOLDER_STATEKEEPING => '.',
        self::CONF_FOLDER_LOGS => 'log',
        self::CONF_FOLDER_TODO => 'todo',
        self::CONF_FOLDER_IN_PROGRESS => 'in_progress',
        self::CONF_FOLDER_DONE => 'done',
        self::CONF_FOLDER_ERROR => 'error',
    );
    //@}

    /**
     * @name Miscellaneous
     */
    //@{
    const DEFAULT_CONF_FILENAME = 'cinbox.ini';         ///< Default filename of configuration file in Inbox folder
    const DEFAULT_TEMP_SUBFOLDER = 'ci-%s';             ///< Name of temp folder. Placeholder will be replaced by (cleaned) Inbox name.
    const DEFAULT_WORK_TIMES_SLEEP = 45;                ///< Seconds to sleep/wait until checking again if WORK_TIMES are due. Must be less-than 60!
    //@}



    /* ========================================
     * PROPERTIES
     * ======================================= */

    /**
     * @name Basics
     */
    //@{
    protected $logger;                                    ///< Logging handler
    protected $config;                                    ///< CIConfig object
    protected $name;
    protected $workTimes;                                 ///< Array of working hours (crontab strings) when to process new Items.
    //@}

    /**
     * @name Folder handling
     */
    //@{
    protected $sourceFolder;                              ///< Base folder where data for this inbox is located
    //@}

    /**
     * @name Temp folder handling
     */
    //@{
    protected $tempFolderRoot;                            ///< operating system folder where temporary files shall be stored. Default is 'sys_get_temp_dir()': /tmp, C:\temp, etc.
    protected $tempFolder;                                ///< Folder where to write temporary files to. Created by 'initTempFiles()'.
    protected $tempReRun = false;                         ///< true/false: indicates whether temp folder already existed, indicating a re-run with unfinished items.
    //@}

    /**
     * @name Item handling
     */
    //@{
    protected $itemList;                                  ///< Array of to-do itemIds (key) and their folder as SplFileInfo object (value)
    protected $itemId;                                    ///< ID of current Item
    protected $item;                                      ///< Item currently loaded/processed
    protected $itemCount;                                 ///< Counts items per run
    //@}



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct($logger)
    {
        // Initialize output/logging:
        $this->logger = $logger;

        // Configuration handler:
        $this->config = new \ArkThis\CInbox\CIConfig($logger);

        // Initialize properties:
        $this->resetItemList();                        // We start empty, but expect more to come ;)
    }


    function __destruct()
    {
        $l = $this->logger;

        $l->logNewline();
        $l->logMsg(_("Clean ending of Inbox..."));

        $this->removeTempFolder();
    }


    /**
     * Returns the logger object used in this class.
     *
     * @retval Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }


    /**
     * @name Temp folder handling.
     */
    //@{

    /**
     * This function cleans up things created during processing.
     * It should be called before ending execution of the Inbox.
     */
    function removeTempFolder()
    {
        $l = $this->logger;

        // Cleanup temp folder (if empty):
        $tempFolder = $this->tempFolder;
        if (is_dir($tempFolder))
        {
            $l->logInfo(sprintf(
                _("Trying to remove temp folder '%s'..."),
                $tempFolder
            ));
            if (Helper::removeEmptySubfolders($tempFolder))
            {
                $l->logInfo(_("Removed temp folder."));
            }
            else
            {
                $l->logMsg(sprintf(
                    _("Could not remove temp folder '%s'. ".
                    "This is okay if some items are still in present in the processing folders."),
                    $tempFolder
                ));
            }
        }

        return true;
    }


    /**
     * Returns the temporary folder for this Inbox.
     *
     * @retval String
     */
    public function getTempFolder()
    {
        return $this->tempFolder;
    }

    //@}


    /**
     * Cleans the item folder list by resetting it to an empty array.
     */
    public function resetItemList()
    {
        $this->itemList = array();
        $this->itemCount = 0;
        reset($this->itemList);
    }


    /**
     * Set the folder where the items (=data-source) for this inbox is located.
     */
    public function setSourceFolder($sourceFolder)
    {
        if (empty($sourceFolder))
        {
            throw new Exception(_("Source folder name empty."));
        }
        if (!file_exists($sourceFolder))
        {
            throw new Exception(sprintf(
                _("Source folder does not exist: '%s'"),
                $sourceFolder
            ));
        }
        if (!is_dir($sourceFolder))
        {
            throw new Exception(sprintf(
                _("Source folder is not a directory: '%s'"),
                $sourceFolder
            ));
        }

        $this->sourceFolder = $sourceFolder;

        return true;
    }


    /**
     * Sets the filename where to write log output to.
     * If empty, then no logfile will be set.
     */
    public function setLogfile($file)
    {
        if (empty($file)) return true;
        return $this->logger->setLogfile($file);
    }


    /**
     * Sets the path and filename of the configuration file.
     * If $configFile is empty, the default config file is assumed in the Inbox's source folder.
     */
    public function setConfigFile($configFile=null)
    {
        $l = $this->logger;
        $sourceFolder = $this->sourceFolder;

        if (empty($configFile))
        {
            if (empty($sourceFolder))
            {
                throw new Exception(sprintf(
                    _("Unable to set config filename to default '%s', ".
                    "because source folder is not set."),
                    self::DEFAULT_CONF_FILENAME
                ));
            }

            // If empty and source folder is set: use default config filename in source_folder
            $configFile = $sourceFolder . DIRECTORY_SEPARATOR . self::DEFAULT_CONF_FILENAME;
        }
        $this->config->setConfigFile($configFile);

        return true;
    }


    /**
     * Set the log output format for Items.
     *
     * @see Logger::setOutputFormat()
     * @see Logger::isValidOutputFormat()
     */
    public function setItemLogstyle($logstyle)
    {
        // If there's nothing to set, don't set anything:
        if (empty($logstyle)) return false;

        Logger::validateOutputFormat($logstyle);

        $this->config->set(self::CONF_ITEM_LOGSTYLE, $logstyle);
        return true;
    }


    /**
     * This loads the INI file into $this->config and initializes placeholders.
     * It does NOT yet apply the config. This is done in applyConfig().
     *
     * @see applyConfig()
     */
    public function loadConfig()
    {
        $config = $this->config;

        $config->loadConfigFromFile();

        // Only basic placeholders resolved here (pre-item information):
        $config->initPlaceholders();

        return true;
    }


    /**
     * Apply the (hopefully already loaded) configuration options that are
     * used in this class.
     * Default values are applied, as configured in getDefaultValues().
     *
     * Important:
     * Config file must be loaded before (see loadConfig()).
     *
     * @see loadConfig()
     * @see CIConfig::getConfigForSection()
     */
    public function applyConfig()
    {
        $l = $this->logger;

        $l->logMsg(_("Applying settings for inbox..."));

        $config = $this->config;
        $configArray = $config->getConfigForSection(self::CONF_SECTION_INBOX);

        try
        {
            // Set defaults before loading from config:
            $l->logInfo(_("Initializing default settings for Inbox..."));

            // Default values must be loaded before 'loadSettings()' in order to have default values instead
            // of empty values where no setting was configured in config file.
            $config->setSettingsDefaults($this->getDefaultValues());
            $config->loadSettings($configArray);
            $l->logDebug(sprintf(
                _("Settings for inbox:\n%s"),
                print_r($config->getSettings(), true))
            );

            // Check for must-exist values:
            $inboxName = $this->getName();
            if (empty($inboxName))
            {
                $l->logError(sprintf(
                    _("No inbox name set in config option '%s'. ".
                    "Please set one."), self::CONF_INBOX_NAME
                ));
                return false;
            }

            $this->getWorkTimes();
        }
        catch (Exception $e)
        {
            $l->logError(sprintf(
                _("Could not load settings for '%s': %s\n%s"),
                self::CONF_SECTION_INBOX,
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            return false;
        }

        $l->logMsg(_("Inbox settings successfully applied."));
        return true;
    }


    /**
     * Name of this Inbox.
     * Must only contain alphanumeric characters, so it can be used in temp foldername.
     */
    public function getName()
    {
        $config = $this->config;

        if (!$config instanceof CIConfig)
        {
            throw new Exception(
                _("Unable to get Inbox name: Config not initialized.")
            );
        }

        $name = $config->get(self::CONF_INBOX_NAME);

        if (empty($name))
        {
            return false;
        }

        return $name;
    }


    /**
     * Reads WORK_TIMES from the config into the class property
     * "this->workTimes".  Basic checks are applied and exceptions thrown if
     * anything is not in order with this setting.
     *
     */
    public function loadWorkTimes()
    {
        $l = $this->logger;
        $config = $this->config;

        $workTimes = $config->get(self::CONF_WORK_TIMES);

        if (empty($workTimes))
        {
            $l->logDebug(sprintf(
                _("No %s set. That's fine. We have nothing to wait for."),
                self::CONF_WORK_TIMES
            ));
            return false;
        }

        if (!is_array($workTimes))
        {
            throw new \InvalidArgumentException(sprintf(
                _("Work times should be an array. Did you add '[]' after '%s' in the config?"),
                self::CONF_WORK_TIMES
            ));
        }

        // From here on, we can assume that $workTimes are set:
        $l->logDebug(sprintf(
            _("Work times set: %s"),
            print_r($workTimes, true)
        ));

        return $workTimes;
    }


    /**
     * Returns the property $this->workTimes.
     *
     * If it's not set yet, it calls loadWorkTimes().
     * Returns "false" if no work times are set, and throws
     * InvalidArgumentException on errors.
     */
    public function getWorkTimes()
    {
        $l = $this->logger;

        if (!isset($this->workTimes))
        {
            $this->workTimes = $this->loadWorkTimes();
        }

        // From here on, we can assume that $workTimes are set:
        $l->logInfo(sprintf(_("Work times set: %s"), print_r($this->workTimes, true)));

        return $this->workTimes;
    }


    /**
     * Returns the default values for Inbox config settings.
     *
     * @see $processingFolders
     */
    public function getDefaultValues()
    {
        $defaultValues = array_merge(
            array(
                self::CONF_PAUSE_TIME => 5,
                self::CONF_ITEMS_AT_ONCE => 10,
                self::CONF_KEEP_FINISHED => 0,
                self::CONF_WAIT_FOR_ITEMS => 5,
                self::CONF_MOVE_LOGFILES => 1,

                self::CONF_FOLDER_TEMP => '/var/cinbox',
            ),
            self::$processingFolders
        );

        return $defaultValues;
    }


    /**
     * Generates a list of item-folders in sourceFolder.
     */
    public function initItemList($sourceFolder)
    {
        /*
         * create an array of the items in the inbox "to do" folder
         * key = itemId
         * value = SplFileInfo object
         */

        $l = $this->logger;
        $config = $this->config;

        $l->logMsg(sprintf(_("Listing item folders in '%s'..."), $sourceFolder));

        // sourceFolder must be set here.
        // If set, than check if it exists and is a folder were already done in 'setSourceFolder()'.
        if (empty($sourceFolder))
        {
            throw new Exception(_("Cannot list items. Value for 'sourceFolder' is not set."));
        }

        // Avoid runtime changes to processing folders:
        $this->validateProcessingFolders(static::$processingFolders, $quiet=true);

        $this->resetItemList();
        $list = Helper::getFolderListing3($sourceFolder);
        ksort($list); // sort filenames alphabetically

        foreach ($list as $key=>$entry)
        {
            if ($entry['isDir'])
            {
                $l->logInfo(sprintf(_("  - Item: '%s'"), $entry['Filename']));
                $this->addItem($entry['Pathname']);
            }
        }

        $this->sortItems();
        $l->logMsg(sprintf(_("Inbox '%s' contains %d items."), $this->getName(), $this->getItemCount()));
        $l->logNewline();

        return true;
    }


    /**
     * Sort item list alphabetically.
     */
    protected function sortItems()
    {
        return ksort($this->itemList);
    }


    /**
     * Adds an item folder to the CInbox' processing list.
     *
     * @param[in]   string  $folder     Full path name of this folder
     */
    protected function addItem($folderName)
    {
        $l = $this->logger;

        // The folder name must be the Item ID:
        $itemId = basename($folderName);
        $this->itemList[$itemId] = $folderName;
    }


    /**
     * Returns the number of items currently in the Inbox in state 'TO DO'.
     */
    public function getItemCount()
    {
        $itemCount = count($this->itemList);
        return $itemCount;
    }



    /**
     * Initializes the Inbox prior to being able to process any items.
     *
     * This includes the following steps:
     *   - loadConfig()
     *   - applyConfig()
     *   - initProcessingFolders()
     *   - initTempFiles()
     *   - initItemList()
     *
     * @retval boolean
     *   Returns True if successful, False if an error occurred.
     */
    public function initInbox()
    {
        if (!$this->loadConfig()) return false;

        if (!$this->applyConfig()) return false;
        if (!$this->initProcessingFolders()) return false;
        if (!$this->initTempFiles()) return false;

        if (!$this->initItemList($this->getProcessingFolder(self::CONF_FOLDER_TODO))) return false;

        return true;
    }


    /**
     *
     */
    public function getSysTempFolder()
    {
        $tempFolderSys = sys_get_temp_dir();            // Temp. folder of the operating system

        if (empty($tempFolderSys))
        {
            throw new Exception(
                _("Could not get operating system's temp folder. 'sys_get_temp_dir()' was empty."
                ));
        }

        if (!is_dir($tempFolderSys))
        {
            throw new Exception(sprintf(_("Problem getting operating system's temp folder. '%s' is not a directory."), $tempFolderSys));
        }

        return $tempFolderSys;
    }


    /**
     * Initialize the use of temporary folder for this inbox.
     *
     * The name for the temp-folder is built by using the inbox-label
     * and sanitizing it as foldername.
     * If the temp folder was created successfully, the property
     * $tempFolder is set to the actual foldername.
     *
     * If the temp-folder already exists, the property $tempReRun
     * is set to True.
     * This can be used throughout the class to know that there
     * are "leftovers" from previous runs :)
     */
    public function initTempFiles()
    {

        $l = $this->logger;
        $config = $this->config;
        $l->logDebug(_("Initializing temp folder..."));

        $this->tempFolderRoot = $config->get(self::CONF_FOLDER_TEMP);

        if (empty($this->tempFolderRoot))
        {
            throw new Exception(
                sprintf(_("Invalid or empty temp folder root: '%s'. Check config variable '%s'?",
                $this->tempFolderRoot,
                $self::CONF_FOLDER_TEMP)));
        }

        // FIXME: Currently only spaces in Inbox name are replaced. Maybe use
        // TaskCleanFilenames object methods for this?
        $inboxName = $this->getName();
        $cleanName = strtolower(str_replace(' ', '_', $inboxName));
        $tempSubFolder = sprintf(self::DEFAULT_TEMP_SUBFOLDER, $cleanName);
        $tempFolder = $this->tempFolderRoot . DIRECTORY_SEPARATOR . $tempSubFolder;

        $l->logMsg(sprintf(_("Temporary folder: '%s'"), $tempFolder));

        if (is_dir($tempFolder))
        {
            // TODO: Check if we have write permissions?
            $l->logMsg(sprintf(_("Temp folder for Inbox '%s' already exists. Possible re-run. We'll be careful."), $inboxName));
            $this->tempReRun = true;
        }
        else
        {
            $l->logDebug(sprintf(_("Creating temp folder: '%s'"), $tempFolder));
            if (!mkdir($tempFolder))
            {
                $l->logError(sprintf(
                    _("Could not create temp folder '%s'. Check access rights? Does parent folder exist?"),
                    $tempFolder));
                return false;
            }
        }

        $this->tempFolder = $tempFolder;
        return true;
    }


    public function initItem()
    {
        $l = $this->logger;
        $config = $this->config;
        $itemId = $this->itemId;

        if (empty($itemId)) throw new Exception(_("Cannot init item: itemId not set."));
        $l->logMsg(sprintf(_("Initializing item '%s'..."), $itemId));

        // Create a new logger instance and pass that one to Item.
        // This allows separate logging of item handling to item-specific logfile.
        // Logfilename(s) are provided by CInbox class.
        $il = clone $l;
        $itemFolder = $this->itemList[$itemId];

        $logFolder = $this->getProcessingFolder(self::CONF_FOLDER_LOGS);
        $moveLogs = $this->config->get(self::CONF_MOVE_LOGFILES) == 1 ? true : false;
        if ($moveLogs) $logFolder = dirname($itemFolder);
        $il->setLogfile($this->getItemLogfile($itemId, $logFolder));

        // Set output format for Item logs:
        $logstyle = $config->get(self::CONF_ITEM_LOGSTYLE);
        if (!is_null($logstyle)) { $il->setOutputFormat($logstyle); }

        $item = new \ArkThis\CInbox\CIItem($itemId, $il, $itemFolder);
        $item->setMoveLogs($moveLogs);
        $this->item = $item;

        try
        {
            $item->getConfig()->loadConfigFromString($config->getConfigResolved());
            $item->initItemSettings();
            $item->setTempFolder($this->tempFolder);
            if (!$item->canStart()) return false;

            $this->switchStatus($item, $item::STATUS_IN_PROGRESS);

            $item->initItem();
        }
        catch (Exception $e)
        {
            $this->switchStatus($item, $item::STATUS_ERROR);
            $il->logException("", $e);
            throw $e;
        }

        return true;
    }


    /**
     * Advance to next to-do item in itemList.
     */
    public function getNextItem()
    {
        $l = $this->logger;

        $limit = $this->config->get(self::CONF_ITEMS_AT_ONCE);
        // ITEMS_AT_ONCE=0 means: No limit.
        if ($limit > 0 && $this->itemCount >= $limit)
        {
            $l->logMsg(sprintf(_("Reached limit of %d items per run."), $limit));
            return false;
        }

        // itemList must be populated here already.
        $nextItem = each($this->itemList);
        if ($nextItem === false) return false;

        $nextItemId = $nextItem['key'];

        $this->itemId = $nextItemId;
        $this->itemCount++;

        $l->logMsg(sprintf(_("Next Item: %d/%d (max %d)"), $this->itemCount, $this->getItemCount(), $limit)); //delme
        return $nextItemId;
    }


    /**
     * Process the current item.
     * @See getNextItem()
     */
    public function processItem()
    {
        $l = $this->logger;
        $item = $this->item;

        try
        {
            if (!$item->process()) throw new Exception(_("Could not process item"));;
        }
        catch (Exception $e)
        {
            $this->item->getLogger()->logException(
                sprintf(
                    _("Problems with '%s'"),
                    $item->getItemId()),
                $e);
            $this->switchStatus($item, $item::STATUS_ERROR);
            throw $e;
        }

        if ($item->tryAgain)
        {
            // Set Item back to to-do folder if it's marked for reset:
            $this->switchStatus($item, $item::STATUS_TODO);
            return true;
        }

        $item->removeTempFolder();
        // TODO: This may be a good place to handle token-creation on "item finished"?
        $this->switchStatus($item, $item::STATUS_DONE);

        // This is required for the garbage collection (KEEP_FINISHED):
        $item->updateTimestamp();
        return true;
    }


    public function finalizeItem()
    {
        $l = $this->logger;
        $item = $this->item;

        return $item->finalize();
    }


    /**
     * Waits a number of seconds and displays a countdown.
     * $update defines how many seconds to wait between updating the countdown output.
     *
     * This will NOT use the logging class to write on screen, since
     * the output is only useful for a user watching. Not in the logs.
     */
    public function pause($seconds, $update=5)
    {
        $l = $this->logger;
        $l->logMsg(sprintf(_("Pausing %d seconds..."), $seconds));

        for ($i = 0; $i < $seconds; $i++)
        {
            ($i % $update == 0) ? printf("%d", $seconds - $i) : printf(".");
            sleep(1);
        }

        printf("0\n");
    }


    /**
     * Returns "true" if processing "is due", given the string in
     * "CONF_WORK_TIMES".
     *
     * Use $isLooped=false to show when next run dates would be, etc.
     */
    public function isDue($workTimes, $isLooped=true)
    {
        $l = $this->logger;

        if (empty($workTimes))
        {
            $l->logDebug(sprintf(
                _("No %s set. Nothing to wait for."),
                self::CONF_WORK_TIMES
            ));
            return true;
        }

        //Interpreting WORK_TIMES, and actually decide whether or not we're in
        //or outside of working hours:
        foreach ($workTimes as $workTime)
        {
            $cron = new \Cron\CronExpression($workTime);

            if (!$isLooped)
            {
                $l->logMsg(sprintf(
                    _("Next run date is: %s (%s)"),
                    $cron->getNextRunDate()->format('Y-m-d H:i:s'),
                    $workTime
                ));
            }

            // If 'now' matches any of the expressions in $workTimes, return true:
            if ($cron->isDue())
            {
                $l->logInfo(sprintf(
                    _("%s expression '%s' is now due!\n Ready to continue.\n\n"),
                    self::CONF_WORK_TIMES,
                    $cron->getExpression()
                ));
                return true;
            }
        }

        // No matching expression. We're NOT due.
        if (!$isLooped)
        {
            $l->logDebug(sprintf(
                _("Current date/time '%s' is outside of working hours set in '%s'.\n I'll wait 😇️"),
                date('Y-m-d H:i:s'),
                self::CONF_WORK_TIMES
            ));
        }

        return false;
    }


    /**
     * This is the main loop that iterates through items in the inbox.
     */
    public function run($forever=false)
    {
        $l = $this->logger;

        $pause = $this->config->get(self::CONF_PAUSE_TIME);
        $waitForItems = $this->config->get(self::CONF_WAIT_FOR_ITEMS);
        $workTimes = $this->getWorkTimes();

        // Show current status regarding work times:
        $this->isDue($workTimes, $isLooped=false);

        // TODO: Could this overflow and cause problems in "forever" mode?
        $errors = 0;

        // ==============================================
        // This is the main loop that picks up new items!
        // ==============================================
        $itemId = $this->getNextItem();
        while ($forever || ($itemId !== false))
        {
            // Check WORK_TIMES and sleep until we're "due":
            if ($this->isDue($workTimes) === false)
            {
                //Print a dot to indicate we're still alive.
                echo "."; // just output this. don't bother to log it.
                sleep(self::DEFAULT_WORK_TIMES_SLEEP);
                continue;
            }


            if ($this->config->hasChanged())
            {
                $l->logWarning(sprintf(_("Config has changed while running. Please restart to load new settings!")));
            }

            if (!empty($itemId))
            {
                $l->logMsg(sprintf(_("Next item to process: '%s'."), $itemId));

                try
                {
                    if ($this->initItem() !== false)
                    {
                        if (!$this->processItem()) throw new Exception(sprintf(
                            _("Could not process item '%s'"),
                            $itemId
                        ));

                        if (!$this->finalizeItem()) throw new Exception(sprintf(
                            _("Could not finalize item '%s'"),
                            $itemId
                        ));
                    }
                }
                catch (Exception $e)
                {
                    // TODO:  FIXME!
                    // Logging here might create duplicate log entries, but disabling it might let
                    // some exceptions unlogged! (e.g. wrong logfile in $this->initItem().
                    $l->logException(sprintf(_("Problem with Item"), $itemId), $e);
                    $errors++;
                }

                $itemId = $this->getNextItem();
                if ($itemId !== false) $this->pause($pause);
            }
            else
            {
                // Garbage collection:
                $this->removeDoneItems();

                // No items (yet). Let's wait if we're in forever-mode:
                if ($forever)
                {
                    $l->logMsg(_("Currently no items to process."));
                    $this->pause($waitForItems);

                    if ($this->initItemList($this->getProcessingFolder(self::CONF_FOLDER_TODO)))
                    {
                        $itemId = $this->getNextItem();
                    }
                }
            }

            // Check if config has changed:
            if ($this->config->monitorConfigFileChanges())
            {
                $l->logInfo(sprintf(_("Config file has changed: %s"), $this->config->getConfigFile()));
            }
        }

        // Garbage collection:
        $this->removeDoneItems();

        return $errors;
    }


    /**
     * Returns the name of a logfile to use, based on an Item ID.
     */
    public function getItemLogfile($itemId, $folder)
    {
        if (empty($itemId)) throw new \InvalidArgumentException(sprintf(_("Item ID required")));

        if (!is_dir($folder)) throw new Exception(sprintf(_("Invalid log folder: '%s'"), $folder));

        $filename = sprintf('%s.log', $itemId);
        $logfile = $folder . DIRECTORY_SEPARATOR . $filename;

        return $logfile;
    }


    /**
     * Returns the actual foldername of a processing folder.
     * $type must match the configuration strings.
     *
     * Example:
     * Logfile folder: $type = CInbox::CONF_FOLDER_LOGS
     */
    public function getProcessingFolder($type)
    {
        $type = strtoupper($type);

        if (!is_dir($this->statusBase))
        {
            throw new Exception(sprintf(_("Status base folder invalid or not set: %s"), $this->statusBase));
        }

        $folder = $this->config->get($type);
        if (empty($folder)) throw new Exception(sprintf(_("No folder configured matching '%s'"), $type));

        $statusFolder = $this->statusBase . DIRECTORY_SEPARATOR . $folder;
        return $statusFolder;
    }

    /**
     * This function translates an Item status to its corresponding
     * processing folder in the CInbox.
     *
     * $status must be listed in CIItem::$itemStates;
     *
     * @retval string
     *  The name of the processing folder representing the given status.
     */
    public function getStatusFolder($status)
    {
        $statusIndex = strtoupper('DIR_' . $status);
        return $this->getProcessingFolder($statusIndex);
    }


    /**
     * Set the base folder for Inbox' state-keeping to $folder.
     * By default, this is configured as CONF_FOLDER_STATEKEEPING option, and equals
     * the Inbox' source folder.
     */
    public function setStatusBaseFolder($folder)
    {
        $l = $this->logger;

        if (!Helper::isAbsolutePath($folder)) $folder = $this->sourceFolder . DIRECTORY_SEPARATOR . $folder;
        $baseFolder = realpath($folder);

        if (!file_exists($baseFolder) || !is_dir($baseFolder))
        {
            throw new Exception(sprintf(_("State-keeping base folder does not exist or is not a folder: %s"), $baseFolder));
        }

        $this->statusBase = $baseFolder;
        $l->logMsg(sprintf(_("State-keeping base folder is '%s'"), $this->statusBase));
        return true;
    }


    /**
     * Make sure that all processing folders are in place and have the right access rights, etc.
     *
     * This method is designed to be called between execution loops to intercept errors caused by
     * runtime-changes to the processing folders.
     * For example: Folder removed or renamed between loops.
     */
    public function validateProcessingFolders($processingFolders, $quiet=true)
    {
        $l = $this->logger;

        $errors = 0;
        $count = 0;
        foreach ($processingFolders as $key=>$entry)
        {
            $processingFolder = $this->getProcessingFolder($key);
            $count++;

            if (!file_exists($processingFolder))
            {
                // TODO: Add option to create these folders. But don't do it automatically!
                $l->logError(sprintf(_("Processing folder for '%s' does not exist: %s"), $key, $processingFolder));
                $errors++;
                continue;
            }

            if (!is_dir($processingFolder))
            {
                $l->logError(sprintf(_("Folder for '%s' is not a directory: %s"), $key, $processingFolder));
                $errors++;
                continue;
            }

            if (!is_writeable($processingFolder))
            {
                $l->logError(sprintf(_("Cannot write to folder for '%s': '%s' - Check access rights?"), $key, $processingFolder));
                $errors++;
                continue;
            }

            // Allow suppressing output for "okay" to avoid cluttering the logs:
            if (!$quiet)
            {
                $l->logInfo(sprintf(_("Processing folder for '%s' is: %s"), $key, $processingFolder));
            }
        }

        if ($errors > 0)
        {
            throw new Exception(sprintf(_("%d/%d processing folders are not valid."), $errors, $count));
        }

        // If we have reached this, there is no error:
        return true;
    }


    /**
     * Checks if all processing folder are present and writable.
     */
    public function initProcessingFolders()
    {
        $l = $this->logger;

        $baseFolder = $this->config->get(self::CONF_FOLDER_STATEKEEPING);
        if (empty($baseFolder))
        {
            throw new Exception(sprintf(_("No state-keeping base folder set. Config option %s"), self::CONF_FOLDER_STATEKEEPING));
        }
        if (!$this->setStatusBaseFolder($baseFolder)) return false;

        // Check if each processing folder is okay:
        $this->validateProcessingFolders(
            static::$processingFolders,
            $quiet=false);

        return true;
    }


    /**
     * Changes the state of an Item to a different target status.
     * Returns the target state folder on success.
     * 'False' if a problem occurred.
     */
    public function switchStatus($item, $status)
    {
        $l = $this->logger;

        $itemId = $item->getItemId();
        $folder = $item->getPathname();

        // TODO: Check if $state is a valid option.
        $targetFolder = $this->getStatusFolder($status) . DIRECTORY_SEPARATOR . basename($folder);
        $l->logDebug(sprintf(_("(%s): Current folder: %s"), $itemId, $folder));
        $l->logDebug(sprintf(_("(%s): Target folder: %s"), $itemId, $targetFolder));

        try
        {
            $item->moveFolder($targetFolder);
            $this->itemList[$itemId] = $targetFolder;
            $l->logMsg(sprintf(_("(%s): Changed status from '%s' to '%s'..."), $this->itemId, basename(dirname($folder)), $status));
        }
        catch (Exception $e)
        {
            $l->logException(sprintf(_("(%s): Could not move folder '%s' to '%s'."), $itemId, $folder, $targetFolder), $e);
        }

        // Write token to trigger external processes:
        $tokenFile = $item->getTokenFilename($status);
        if (!empty($tokenFile))
        {
            // Encode data in JSON (but don't escape slashes):
            $data = $item->getTokenData(
                $flags = JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            );

            $result = $item->writeTokenFile($tokenFile, $data);
            if ($result)
            {
                $l->logMsg(sprintf(
                    _("Token '%s' written to: '%s'."),
                    $status,
                    $tokenFile
                ));
            }
        }

        return $targetFolder;
    }


    /**
     * Remove Items that have been successfully processed.
     * Items are deleted if they are older than CONF_KEEP_FINISHED days.
     */
    public function removeDoneItems()
    {
        $l = $this->logger;

        $keepFinished = $this->config->get(self::CONF_KEEP_FINISHED);
        $folder = $this->getProcessingFolder(self::CONF_FOLDER_DONE);
        $count = 0;

        $l->logNewline();
        $l->logMsg(sprintf(_("Removing finished Items older than %d days..."), $keepFinished));

        $list = Helper::getFolderListing3($folder);
        ksort($list); // sort filenames alphabetically

        foreach ($list as $key=>$entry)
        {
            $folderName = $entry['Pathname'];
            $days = (time() - $entry['MTime']) / 60 / 60 / 24;        // Conversion: Seconds to days
            $l->logInfo(sprintf(
                _("Item: %s (%d days old)"),
                basename($folderName),
                $days)
            );

            if ($days > $keepFinished)
            {
                if (is_dir($folderName)) {
                    $l->logMsg(sprintf(
                        _("Deleting finished Item: %s"),
                        basename($folderName))
                    );
                    // Only remove it, if it's a folder. Files directly in the "done" folder will stay:
                    if (Helper::removeFolder($folderName)) $count++;
                } else {
                    $l->logMsg(sprintf(_("Ignoring file '%s' (not an Item). Delete it manually if desired."), basename($folderName)));
                }
            }
        }

        $l->logMsg(sprintf(_("Deleted %d items."), $count));
        $l->logNewline();
    }


    /*
    // TODO
    - Create textfile on success/error?
     */

}

?>
