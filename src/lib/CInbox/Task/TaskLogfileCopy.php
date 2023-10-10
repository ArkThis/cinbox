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
use \ArkThis\Helper;
use \Exception as Exception;


/**
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
class TaskLogfileCopy extends CITask
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Logfile copy';

    // Task specifics:
    const ONCE_PER_ITEM = true;                                 // True: Run this task only once per item.

    /**
     * @name Task Settings
     * Names of config settings used by this task
     */
    //@{
    const CONF_LOG_COPY_DIR = 'LOG_COPY_DIR';                   ///< Folder where to place the logfile in.
    const CONF_LOG_COPY_NAME = 'LOG_COPY_NAME';                 ///< Filename to rename the logfile to.
    //@}



    /* ========================================
     * PROPERTIES
     * ======================================= */

    private $targetDir;                                         ///< @see #CONF_LOG_COPY_DIR
    private $targetName;                                        ///< @see #CONF_LOG_COPY_NAME
    private $targetFile;                                        ///< Final logfile target path+filename.



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);
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

        $this->targetDir = $config->get(self::CONF_LOG_COPY_DIR);
        $this->targetName = $config->get(self::CONF_LOG_COPY_NAME);

        // Task is optional, therefore it's okay if its settings are empty:
        if (empty($this->targetDir) && empty($this->targetName)) return $this->skipIt();

        // No target-dir given: Just rename logfile, don't move it:
        if (empty($this->targetDir)) $this->targetDir = dirname($l->getLogfile());
        if (empty($this->targetDir))
        {
            // If we *still* have no log folder yet, something's wrong:
            $l->logError(_("Logfile foldername empty/not set yet. This should not happen."));
            $this->setStatusConfigError();
            return false;
        }

        // Keep filename if no alternative is configured:
        if (empty($this->targetName)) $this->targetName = basename($l->getLogfile());
        if (empty($this->targetName))
        {
            // If we *still* have no filename yet, something's wrong:
            $l->logError(_("Logfile name empty/not set yet. This should not happen."));
            $this->setStatusConfigError();
            return false;
        }

        // Concatenate folder + filename as actual file destination:
        $this->targetFile = $this->targetDir . DIRECTORY_SEPARATOR . $this->targetName;

        $l->logMsg(sprintf(
                    _("Logfile target: %s"),
                    $this->targetFile
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

        if (!is_dir($this->targetDir))
        {
            $l->logMsg(sprintf(
                _("Target folder for logfile doesn't exist yet: %s"),
                $this->targetDir
                ));
        }

        if (!Helper::touchFile($this->targetFile)) return false;


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

        // Stuff should be init and checked now. So, do the actual copy:
        if (!$this->copyLogfile($l->getLogfile(), $this->targetFile))
        {
            $this->setStatusError();
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

        // TODO: Add here what the task is supposed to do.

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
     * Just copies the logfile from A to B.
     */
    protected function copyLogfile($sourceFile, $targetFile)
    {
        $l = $this->logger;

        $l->logMsg(sprintf(
            _("Copying logfile from '%s' to '%s'."),
            $sourceFile,
            $targetFile
            ));

        if (copy($sourceFile, $targetFile) && file_exists($targetFile))
        {
            $l->logMsg(sprintf(
                _("Successfully copied logfile to '%s'"),
                $targetFile
                ));
            return true;
        }

        $l->logErrorPhp(_("Failed to copy logfile."));
        return false;
    }


    //@}

}

?>
