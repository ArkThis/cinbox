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
use \RuntimeException as RuntimeException;


/**
 * Saves a directory listing in CSV format.
 * Relies on TaskDirListing to create a list of files/folders of this item.
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
class TaskDirListCSV extends TaskDirListing
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Directory listing (CSV)';

    // Line key/value formatting to use for CSV output.
    //@{
    const CSV_STYLE_LIBRE = '"%s","%s","%s","%s","%s","%s","%s"'. "\n";   ///< Works for everyone (except Excel)
    const CSV_STYLE_EXCEL = '"%s";"%s";"%s";"%s";"%s";"%s";"%s"'. "\r\n"; ///< Optimized for MS-Excel.
    //@}



    /* ========================================
     * PROPERTIES
     * ======================================= */

    protected static $formatFileTime = DateTime::ISO8601;
    protected $csvLineFormat = self::CSV_STYLE_LIBRE;

    protected $csvHeaderLine;
    protected static $csvHeaderFields = array(
        'Type', 'Path', 'Filename',
        'Bytes',
        'CTime', 'MTime', 'ATime'
    );



    /* ========================================
     * METHODS
     * ======================================= */

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

        $l->logDebug(sprintf(
            _("Initializing CSV header with these components:\n%s\n%s"),
            $this->csvLineFormat,
            implode(',', self::$csvHeaderFields)
        ));

        // Populate header line with strings from Array into csvLineFormat
        // printf-mask:
        $this->csvHeaderLine = vsprintf(
            $this->csvLineFormat,
            self::$csvHeaderFields
        );

        $l->logMsg(sprintf(
            _("Using CSV header line: %s"),
            $this->csvHeaderLine
        ));

        return true;
    }


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
    public function getFilename() # TODO: Why is this here and not in parent class TaskDirlist?
    {
        $fileName = $this->dirListFile;
        return $fileName;
    }


    public function getDirListAsCSV($dirList)
    {
        $l = $this->logger;

        $csvHeaderLine = $this->csvHeaderLine;
        $csvLineFormat = $this->csvLineFormat;

        if (empty($csvHeaderLine))
        {
            $msg = _("CSV Header line not initialized/empty!");
            $l->logError($msg);
            throw new RuntimeException($msg);
        }

        $dirListing = $csvHeaderLine; // Start with the header as first line.
        foreach ($dirList as $entry)
        {
            // Properties listed:
            $dirListing .= sprintf(
                    // CSV fields (names and order), see: $this->csvHeaderFields
                    $csvLineFormat,
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
