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
use \ArkThis\CInbox\CIItem;
use \Exception as Exception;
use \RecursiveIteratorIterator as RecursiveIteratorIterator;
use \RecursiveDirectoryIterator as RecursiveDirectoryIterator;


/**
 * Base class for preparing a directory listing.
 *
 *
 * @author Peter Bubestinger-Steindl (pb@av-rd.com)
 * @copyright
 *  Copyright 2018 AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.av-rd.com/products/cinbox/">CInbox product website</a>
 *  - <a href="http://www.av-rd.com/">AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
abstract class TaskDirListing extends CITask
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Directory listing (base)';

    // Task specifics:
    const ONCE_PER_ITEM = true;             // True: Run this task only once per item.

    // Names of config settings used by a task must be defined here.
    const CONF_DIRLIST_FILE = 'DIRLIST_FILE';



    /* ========================================
     * PROPERTIES
     * ======================================= */

    protected $tempFile;
    protected $dirListFilename;             // Filename (without path) where to store directory listing output in.
    protected $dirListFile;                 // Filename with path

    protected $dirList;                     // List as array
    protected $dirListing;                  // List as formatted text



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);
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
        $folder = $this->CIFolder;

        // TODO: Move this to the task that actually writes the file format! (TaskDirListCSV, for example).
        //       This then allows to have multiple tasks writing dirlisting in different output formats.
        // The config only contains the filename, so we must add the path:
        $dirListFile = $folder->getBaseFolder() . DIRECTORY_SEPARATOR . $this->dirListFilename;

        $l->logDebug(sprintf(_("Directory listing output: '%s'"), $dirListFile)); // DElme
        $this->dirListFile = $dirListFile;

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
        $folder = $this->CIFolder;

        // Task is optional:
        if ($this->skip()) return true;

        $l->logMsg(sprintf(_("Creating directory listing for '%s'..."), $folder->getPathname()));
        $this->dirList = $this->generateDirListArray($folder->getPathname());

        // Must return true on success:
        return true;
    }


    /**
     * Load settings from config that are relevant for this task.
     *
     * @retval boolean
     *  True if everything went fine. False if not.
     */
    protected function loadSettings()
    {
        if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        // TODO: Sanity check values here.

        $this->dirListFilename = $config->getFromArray(CIItem::CONF_SECTION_ITEM, self::CONF_DIRLIST_FILE);
        // Task is optional, therefore it's okay if setting is empty:
        if(empty($this->dirListFilename))
        {
            $this->skipIt();
            return true;
        }
        $l->logDebug(sprintf(_("Filename base for directory listing: %s"), $this->dirListFilename));

        // Must return true on success:
        return true;
    }



    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Traverses the directory structure starting in '$folderName', and
     * returns an array, alphabetically sorted by filename, that contains
     * key=filename, value=SplFileInfo Object.
     */
    public static function generateDirListArray($folderName)
    {
        if (!is_dir($folderName))
        {
            throw new Exception(sprintf(_("generateDirListArray: '%s' is not a directory."), $folderName));
        }

        $di = new RecursiveDirectoryIterator($folderName);
        $entries = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::SELF_FIRST);
        $dirList = array();
        foreach($entries as $filename => $entry)
        {
            $basename = $entry->getFilename();
            // Skip '.' and '..':
            if (strcmp($basename, '.') == 0 || strcmp($basename, '..') == 0) continue;

            $dirList[$filename] = $entry;
        }
        ksort($dirList);

        return $dirList;
    }


    /**
     * Writes the contents of $this->dirListing into a file.
     * It will not overwrite an existing file.
     */
    protected function saveToFile($fileName)
    {
        $l = $this->logger;

        if (empty($fileName))
        {
            $l->logError(_("Cannot save to file: No filename given."));
            $this->setStatusError();
            return false;
        }

        $dirListing = $this->dirListing;
        if (empty($dirListing))
        {
            $l->logError(_("Cannot save to file: Directory listing empty."));
            $this->setStatusError();
            return false;
        }

        if (is_writeable($fileName))
        {
            // Existing file will not be overwritten. This is not an error, so we return 'true'.
            $l->logMsg(sprintf(_("File already exists. Will not overwrite '%s'."), $fileName));
            return true;
        }

        // TODO: What if file already exists?
        $l->logInfo(sprintf(_("Saving directory listing to '%s'..."), $fileName));

        $result = file_put_contents($fileName, $dirListing);
        if ($result === false)
        {
            $l->logError(sprintf(_("Could not write directory listing to '%s'. Please check access rights."), $fileName));
            $this->setStatusError();
            return false;
        }

        $l->logMsg(sprintf(_("Wrote directory listing to '%s'."), $fileName));

        return true;
    }



}

?>
