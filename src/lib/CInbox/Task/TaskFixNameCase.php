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
use \SplFileInfo as SplFileInfo;


/**
 * This Task is used to change/rename filenames/foldernames to upper/lowercase.
 *
 * @author Peter Bubestinger-Steindl (cinbox (at) ArkThis.com)
 * @copyright
 *  Copyright 2026 ArkThis AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.ArkThis.com/products/cinbox/">CInbox product website</a>
 *  - <a href="http://www.ArkThis.com/">AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
class TaskFixNameCase extends TaskFilesMatch
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    const TASK_LABEL = 'Fix name case';     ///< Human readable task name/label.

    /**
     * @name Task Settings
     * Names of config settings used by this task
     */
    //@{
    const CONF_NAMECASE_UPPER = 'NAMECASE_UPPER';       ///< Glob patterns to uppercase *whole* file/foldernames.
    const CONF_NAMECASE_LOWER = 'NAMECASE_LOWER';       ///< Glob patterns to lowercase *whole* file/foldernames.

    const CONF_NAMECASE_UPPER_SUFFIX = 'NAMECASE_UPPER_SUFFIX';   ///< Change extension case only for those (.JPG, .HTML, etc)
    const CONF_NAMECASE_LOWER_SUFFIX = 'NAMECASE_LOWER_SUFFIX';   ///< Change extension case only for those (.jpg, .html, etc)
    //@}

    // Valid methods for string manipulation here:
    // At the moment, this is only upper/lower case functions.
    public static $NAMECASE_ALLOWED = array(
        'strtoupper',
        'strtolower',
    );


    /* ========================================
     * PROPERTIES
     * ======================================= */

    // Class properties are defined here.
    protected $nc_upper;                        ///< @see #CONF_NAMECASE_UPPER
    protected $nc_lower;                        ///< @see #CONF_NAMECASE_LOWER
    protected $nc_upper_suffix;                 ///< @see #CONF_NAMECASE_UPPER_SUFFIX
    protected $nc_lower_suffix;                 ///< @see #CONF_NAMECASE_LOWER_SUFFIX


    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

        // Define default settings.
        // By default /nothing/ is renamed.
        $this->nc_upper = null;
        $this->nc_lower= null;
        $this->nc_upper_suffix = null;
        $this->nc_lower_suffix= null;
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
        $setting = null;

        // ---------------------------
        $setting = $config->get(self::CONF_NAMECASE_UPPER);
        // Task is optional, therefore it's okay if setting is empty:
        if(!empty($setting))
        {
            // TODO: Set defaults if no config is given?
            if (!$this->optionIsArray($setting, self::CONF_NAMECASE_UPPER)) return false;
            $l->logDebug(sprintf(_("Force uppercase names: %s"), implode(', ', $setting)));
            $this->nc_upper = $setting;
        }

        // ---------------------------
        $setting = $config->get(self::CONF_NAMECASE_LOWER);
        // Task is optional, therefore it's okay if setting is empty:
        if(!empty($setting))
        {
            // TODO: Set defaults if no config is given?
            if (!$this->optionIsArray($setting, self::CONF_NAMECASE_LOWER)) return false;
            $l->logDebug(sprintf(_("Force lowercase names for: %s"), implode(', ', $setting)));
            $this->nc_lower = $setting;
        }

        // ---------------------------
        $setting = $config->get(self::CONF_NAMECASE_UPPER_SUFFIX);
        // Task is optional, therefore it's okay if setting is empty:
        if(!empty($setting))
        {
            // TODO: Set defaults if no config is given?
            if (!$this->optionIsArray($setting, self::CONF_NAMECASE_UPPER_SUFFIX)) return false;
            $l->logDebug(sprintf(_("Force uppercase suffix for: %s"), implode(', ', $setting)));
            $this->nc_upper_suffix = $setting;
        }

        // ---------------------------
        $setting = $config->get(self::CONF_NAMECASE_LOWER_SUFFIX);
        // Task is optional, therefore it's okay if setting is empty:
        if(!empty($setting))
        {
            // TODO: Set defaults if no config is given?
            if (!$this->optionIsArray($setting, self::CONF_NAMECASE_LOWER_SUFFIX)) return false;
            $l->logDebug(sprintf(_("Force lowercase suffix for: %s"), implode(', ', $setting)));
            $this->nc_lower_suffix = $setting;
        }

        if (empty($this->nc_upper) &&
            empty($this->nc_lower) &&
            empty($this->nc_upper_suffix) &&
            empty($this->nc_lower_suffix))
        {
            // Nothing to check:
            $this->skipIt();
        }

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
        $l = $this->logger;

        if (!parent::run()) return false;

        // ==== Change whole filenames:
        $this->doRename($this->nc_upper, 'to UPPERCASE', 'strtoupper', $suffix_only = false);
        $this->doRename($this->nc_lower, 'to lowercase', 'strtolower', $suffix_only = false);

        // ==== Change only filename extensions:
        $this->doRename($this->nc_upper_suffix, 'to UPPERCASE suffix', 'strtoupper', $suffix_only = true);
        $this->doRename($this->nc_lower_suffix, 'to lowercase suffix', 'strtolower', $suffix_only = true);


        // If we had no error so far: Success!
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
     *
     * Default type is 'protected'. Use 'public' functions only where necessary.
     */
    //@{


    /**
     * Rename a file from $fileIn to $fileOut.
     * NOTE: this is (currently) identical to TaskCleanFilenames->renameFile().
     *       Might be moved to TaskFilesMatch?
     */
    protected function renameFile($fileIn, $fileOut)
    {
        $l = $this->logger;
        $l->logDebug(sprintf(_("Renaming '%s' to '%s'..."), $fileIn, $fileOut));

        if (!rename($fileIn, $fileOut))
        {
            throw new Exception(sprintf(_("Could not rename '%s' to '%s'."), $fileIn, $fileOut));
        }

        return true;
    }


    /**
     * Applies $userFunc string transformation to filenames matching $patterns.
     *
     * @param[in] Array   $patterns     Multiple glob-patterns to match.
     * @param[in] String  $msg          Message string to output.
     * @param[in] Callable $userFunc    Callable function name to apply (eg 'strtoupper', 'strtolower')
     * @param[in] Boolean $suffix_only  true: rename only file extension/suffix.
     */
    protected function doRename($patterns, $msg, callable $userFunc, $suffix_only = false)
    {
        $l = $this->logger;

        if (empty($patterns))
        {
            // Nothing to do?
            // TODO: log message.
            return true;
        }

        if ($this->isAllowedUserFunc($userFunc))
        {
            $l->logDebug(sprintf(
                _("Callable transformation function name is valid/allowed: %s"),
                $userFunc
            ));
        }

        $l->logMsg(sprintf(
            _("Renaming %s: '%s' ..."),
            $msg,
            implode(', ', $patterns)
        ));

        // Generate a file listing and sort it alphabetically:
        $all = $this->getMatchingFiles($this->CIFolder, $patterns);
        sort($all);

        $count = 0;
        $error = 0;
        foreach ($all as $file)
        {
            $fileInfo = pathinfo($file);    // Get filename components the easy way :)
            print_r($fileInfo); //DELME

            // This is the part where any string-transformation function is called
            // by string ($userFunc):
            if ($suffix_only)
            {
                // Rename only the file's suffix/extension:
                $suffix = call_user_func($userFunc, $fileInfo['extension']);
                $filename = $fileInfo['filename'];
                $clean = $filename .'.'. $suffix;
            } else {
                // Rename the whole file basename (incl. extension):
                $clean = call_user_func($userFunc, $fileInfo['basename']);
            }

            $fileIn = $file;
            $fileOut = dirname($file) . DIRECTORY_SEPARATOR . $clean;

            // Show renaming that will occur:
            if (strcmp($fileIn, $fileOut) != 0)
            {
                $count++;
                if (!$this->renameFile($fileIn, $fileOut))
                {
                    $error++;
                }

                $l->logMsg(sprintf(_("Renaming filename '%s' to '%s'."), $fileIn, $clean));
                $l->logDebug(sprintf(_("  - In:  '%s'"), $fileIn));
                $l->logDebug(sprintf(_("  - Out: '%s'"), $fileOut));
            }
        }

        if ($count > 0)
        {
            $l->logMsg(sprintf(_("Renamed %d names."), $count));
        }

        if ($error > 0)
        {
            $l->logError(sprintf(_("Could not rename %d names."), $error));
            $this->setStatusPBCT();
            return false;
        }
    }


    /**
     * Check if a 'callable' string is listed as allowed transformation
     * function here.
     *
     * @see: self::$NAMECASE_ALLOWED
     */
    protected function isAllowedUserFunc($userFunc)
    {
        $allowed = self::$NAMECASE_ALLOWED;

        if (!in_array($userFunc, $allowed))
        {
            throw new Exception(sprintf(
                _("invalid function string used: '%s'. Allowed are: %s"),
                $userFunc,
                implode(', ', $allowed)
            ));
        }

        return true;
    }

    //@}

}

?>
