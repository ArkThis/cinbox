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
use \RuntimeException as RuntimeException;


/**
 * Abstract base for tasks that need to deal with files from/to an S3 storage.
 *
 * It is able to use metadata stored in an ".s3info" file (created by AV-RD
 * preprocessing script).
 *
 * NOTE: Current implementation is limited to 1 .s3info file per Item only.
 *
 *
 * @author Peter Bubestinger-Steindl (pb@av-rd.com)
 * @copyright
 *  Copyright 2019 AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.av-rd.com/products/cinbox/">CInbox product website</a>
 *  - <a href="http://www.av-rd.com/">AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
abstract class TaskS3Info extends CITask
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    const TASK_LABEL = 'S3 Info (abstract)';                ///< Human readable task name/label.

    /**
     * @name Task Settings
     * Names of config settings used by this task
     */
    //@{
    const CONF_S3_INFO = 'S3_INFO';                         ///< Filename (mask) where s3info is stored (eg "[@ITEMID@].s3info").
    //@}



    /* ========================================
     * PROPERTIES
     * ======================================= */

    // Class properties are defined here.

    private $s3Info;                                        ///< Associative array with parsed contents from $s3InfoFile.
    private $s3InfoFile;                                    ///< Textfile with name=value data stored from S3 source.



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


        // -------
        $this->s3InfoFile = $config->get(self::CONF_S3_INFO);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->s3InfoFile)) return $this->skipIt();
        $l->logDebug(sprintf(
                    _("S3 info file: '%s'"),
                    $this->s3InfoFile
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

        $this->s3InfoFile = $this->resolveS3InfoFile($this->s3InfoFile);
        $this->s3Info = $this->loadS3InfoFile($this->s3InfoFile);


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
        // TODO: Add here what the task is supposed to do.

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

        // TODO: Optional. Do things here that need to be done *after*
        //       the actual task has finished.
        //       For example clean-up things or so.

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
     * Resolves the absolute path of the S3 info file.
     * This allows using Item-relative path locations in the config file.
     */
    protected function resolveS3InfoFile($s3InfoFile)
    {
        $l = $this->logger;

        $s3InfoFile = Helper::getAbsoluteName($s3InfoFile, $this->sourceFolder);
        $l->logInfo(sprintf(_("Absolute filename of S3 info file: '%s'"), $s3InfoFile)); //FIXME: logDebug!

        return $s3InfoFile;
    }


    /**
     * Load and parse data stored in the S3 Infofile into the variable:
     * '$this->s3Info' as associative array.
     * It expects the data in the .s3info file to be INI formatted.
     */
    protected function loadS3InfoFile($s3InfoFile)
    {
        $l = $this->logger;

        if (!file_exists($s3InfoFile)) throw new RuntimeException(
            sprintf(_("S3 info file does not exist: '%s'"), $s3InfoFile));

        $s3InfoRaw = file_get_contents($s3InfoFile);
        $s3Info = parse_ini_string($s3InfoRaw, $process_sections=false, INI_SCANNER_NORMAL);

        if (is_array($s3Info))
        {
            $l->logInfo(sprintf(_("S3 info (loaded):\n%s"), print_r($s3Info, true))); //FIXME: logDebug!
        }

        return $s3Info;
    }

    //@}



}

?>
