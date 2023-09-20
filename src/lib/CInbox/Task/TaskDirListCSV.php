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
use \DateTime as DateTime;

use \ArkThis\CInbox\CIFolder;
use \ArkThis\CInbox\CIItem;
use \Exception as Exception;


/**
 * Saves a directory listing in CSV format.
 * It adds the file suffix 'self::FILE_SUFFIX', which defaults to "csv".
 * Relies on TaskDirListing to create a list of files/folders of this item.
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
class TaskDirListCSV extends TaskDirListing
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Directory listing (CSV)';
    const FILE_SUFFIX = 'csv';



    /* ========================================
     * PROPERTIES
     * ======================================= */

    protected static $formatFileTime = DateTime::ISO8601;



    /* ========================================
     * METHODS
     * ======================================= */

    /**
     * Perform the actual steps of this task.
     */
    public function run()
    {
        if (!parent::run()) return false;

        $l = $this->logger;

        // Task is optional:
        if ($this->skip()) return true;

        $this->dirListing = $this->getDirListAsCSV($this->dirList);
        $l->logDebug(sprintf(_("Dir listing (CSV):\n%s"), $this->dirListing));
        $this->saveToFile($this->getFilename());

        // Must return true on success:
        $this->setStatusDone();
        return true;
    }


    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Returns the actual filename (including path) for the directory listing.
     */
    public function getFilename()
    {
        $fileName = $this->dirListFile .'.'. self::FILE_SUFFIX;
        return $fileName;
    }


    public static function getDirListAsCSV($dirList)
    {
        $dirListing = '"Type","Path","Filename","Bytes","CTime","MTime","ATime"' . "\n";
        foreach ($dirList as $entry)
        {
            // Properties listed:
            $dirListing .= sprintf(
                    // CSV order: "Type","Path","Filename","Size","CTime","MTime","ATime"'
                    '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                    $entry->getType(),
                    $entry->getPath(),
                    $entry->getFilename(),
                    sprintf("%u", $entry->getSize()),
                    date(self::$formatFileTime, $entry->getCTime()),
                    date(self::$formatFileTime, $entry->getMTime()),
                    date(self::$formatFileTime, $entry->getATime())
                    );
        }

        return $dirListing;
    }



}

?>
