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

use \ArkThis\CInbox\Task\CITask;
use \Exception as Exception;


/**
 * Checks if entries in this folder (files/subfolders) exist, according
 * to a given glob pattern.
 *
 * This class is abstract and should be used as parent class for tasks
 * that require checking existence of certain files.
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
abstract class TaskFilesMatch extends CITask
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Matching filenames (abstract)';



    /* ========================================
     * PROPERTIES
     * ======================================= */



    /* ========================================
     * METHODS
     * ======================================= */

    /**
     * Prepare everything so it's ready for processing.
     */
    public function init()
    {
        if (!parent::init()) return false;

        // Must return true on success:
        return true;
    }



    /**
     * @name Task-specific methods
     */
    //@{

    /**
     * Returns array with files/folders within the folder that match
     * the glob patterns in '$patterns'.
     */
    protected function getMatchingFiles($CIFolder, $patterns)
    {
        $l = $this->logger;

        if (empty($patterns)) return array();

        $source = $CIFolder->getPathname() . DIRECTORY_SEPARATOR;

        $matching = array();
        foreach ($patterns as $pattern)
        {
            $result = glob($source . $pattern);
            $matching = array_merge($matching, $result);
        }

        return $matching;
    }


    /**
     * Returns array with files/folders within the folder that do *not* match
     * the glob patterns in '$patterns'.
     */
    protected function getNonMatchingFiles($CIFolder, $patterns)
    {
        $l = $this->logger;

        // Make a list of all files (!) in this folder (for later comparison):
        $all = $this->getMatchingFiles($CIFolder, array('*'));
        // Make a list of files matching the valid-files pattern:
        $matching = $this->getMatchingFiles($CIFolder, $patterns);

        // Create a diff to see which files are not matched as valid:
        sort($all);
        sort($matching);
        $diff = array_diff($all, $matching);

        // Clean array keys to avoid confusion for caller:
        sort($diff);

        if (!empty($diff))
        {
            $l->logDebug(sprintf(_("Not matched as valid:\n%s"), print_r($diff, true)));
        }

        return $diff;
    }


    /**
     * Checks for missing entries of files/folders in $CIFolder
     * defined by $patterns.
     *
     * @retval integer
     *  Returns the number of files missing.
     * @retval boolean
     *  If $pattern is empty, False is returned.
     */
    protected function hasMissingFiles($CIFolder, $patterns)
    {
        $l = $this->logger;

        if (empty($patterns))
        {
            // No pattern = no filter. No missing files:
            $l->logInfo(_("No filter for missing files/folders set. Fine."));
            return false;
        }

        $missing = 0;
        foreach ($patterns as $pattern)
        {
            $files = $this->getMatchingFiles($CIFolder, array($pattern));

            if (empty($files))
            {
                $l->logMsg(sprintf(
                            _("Missing files/folders in '%s': %s"),
                            $CIFolder->getSubDir(),
                            $pattern));
                $missing++;
            }
            else
            {
                $l->logMsg(sprintf(
                            _("Folder '%s' contains %d files/subfolders that match '%s'. Good."),
                            $CIFolder->getSubDir(),
                            count($files),
                            $pattern));
            }
        }
        return $missing;
    }

    //@}



}

?>
