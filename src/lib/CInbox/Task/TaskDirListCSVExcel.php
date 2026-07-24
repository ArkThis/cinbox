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
use \ArkThis\CInbox\CIItem;

/**
 * Same as TaskDirlistCSV - but optimized so the created CSVs handle well in
 * Microsoft Excel.
 *
 * LibreOffice treats CSVs way better and more consistent, but Excel needs
 * extra quirks-stuff to work. ;P
 *
 * @author Peter Bubestinger-Steindl (cinbox (at) ArkThis.com)
 * @copyright
 *  Copyright 2026 ArkThis AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.ArkThis.com/products/cinbox/">CInbox product website</a>
 *  - <a href="https://github.com/ArkThis/cinbox/">CInbox source code</a>
 *  - <a href="http://www.ArkThis.com/">ArkThis AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
class TaskDirListCSVExcel extends TaskDirListCSV
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Directory listing (CSV for Excel)';



    /* ========================================
     * PROPERTIES
     * ======================================= */

    protected $csvLineFormat = self::CSV_STYLE_EXCEL;



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

        // Initialize defaults:
        // -----------------------------
        // DO write a BOM by default:
        $this->dirListBOM = true;
    }


    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    // None at the moment that differ from TaskDirListCSV. :D
}

?>
