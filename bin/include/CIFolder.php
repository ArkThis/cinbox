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


require_once('include/Helper.php');
require_once('include/CIConfig.php');


/**
 * This is a wrapper around a physical folder.
 * It is used to provide common functionality required across folders of Items
 * in CInbox.
 *
 * It keeps track of folder hierarchies and applies them to handle the
 * settings read from a configfile so that each folder inherits its settings properly.
 *
 * Since target folders for Item subfolders can be configured individually, respecting
 * inheritance and relative paths, this class also takes care of putting together
 * the actual target path.
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
class CIFolder
{

    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Config options:
    const CONF_TARGET_FOLDER = 'TARGET_FOLDER';         // Target folder where to copy data from this folder to.

    // Miscellaneous:
    const CHANGELOG_FILE = 'folder_changelog.txt';      // Textfile to monitor folder changes in.



    /* ========================================
     * PROPERTIES
     * ======================================= */

    protected $logger;                          // Logging handler
    protected $moveLogs = false;                // Move logfiles too when moving folder (@see moveFolder())

    protected $folderObj;                       // SplFileInfo object of related folder
    protected $config;                          // CIConfig object

    protected $tempFolder;                      // Folder where to write temporary files to.
    // Must already exist and should be known to CIItem, so CIFolders can share files between runs.
    protected $changelog = null;                // Name of directory listing textfile to monitor folder changes (cooloff).

    protected $parentItem;                      // CIItem object that this folder belongs to.
    protected $parentFolder;                    // CIFolder object that this folder is a subfolder of.

    protected $hasTargetFolder = null;          // True/false if this object has a TARGET_FOLDER configured in configfile.



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$logger, $folderName, $baseFolder=null)
    {
        $this->logger = $logger;                    // Init. output/logging

        if (!$this->setFolder($folderName))
        {
            throw new Exception(sprintf(_("CIFolder: Invalid folder '%s'"), $folderName));
        }

        if (empty($baseFolder)) $baseFolder = dirname($folderName);
        $this->setBaseFolder($baseFolder);

        // Create config handler:
        $this->config = new CIConfig($logger);

        $logger->logDebug(sprintf(_("New folder '%s' (%s)"), $folderName, $this->getSubDir()));
    }


    /**
     * Set the folder that this CIFolder object represents.
     * Populates the property "$folderObj".
     * Returns 'true' on success. Throws exceptions on errors.
     */
    public function setFolder($folderName)
    {
        $l = $this->logger;

        if (empty($folderName))
        {
            throw new Exception(_("Name of folder cannot be empty."));
        }
        if (!is_dir($folderName))
        {
            throw new Exception(sprintf(_("Folder '%s' is not a directory."), $folderName));
        }

        // Prepare folder attributes:
        $folderObj = new SplFileInfo($folderName);
        if (!$folderObj instanceof SplFileInfo)
        {
            throw new Exception(sprintf(_("Could not load folder '%s' as SplFileInfo object."), $folderName));
        }

        $l->logDebug(print_r($folderObj, true));
        $this->folderObj = $folderObj;

        return true;
    }


    /**
     * Renames/moves the current folder while making sure that all internal things stay working.
     * Parent folders for target must be created before.
     * Returns 'true' on success. Throws exceptions on errors.
     */
    public function moveFolder($targetFolder)
    {
        if (empty($targetFolder)) throw new Exception(_("Empty foldername given."));

        $sourceFolder = $this->getPathname();
        if (!file_exists($sourceFolder))
        {
            throw new Exception(sprintf(_("Cannot move folder. Folder does not exist: '%s'."), $sourceFolder));
        }

        if (file_exists($targetFolder))
        {
            throw new Exception(sprintf(_("Cannot move folder. Target already exists: '%s'."), $targetFolder));
        }

        if (!is_writeable(dirname($targetFolder)))
        {
            throw new Exception(sprintf(_("Cannot move folder. Parent folder is not writeable or does not exist: '%s'."), dirname($targetFolder)));
        }

        if (!rename($sourceFolder, $targetFolder))
        {
            throw new Exception(sprintf(_("Could not move folder '%s' to '%s'."), $sourceFolder, $targetFolder));
        }

        // We've moved the folder, now make sure everyhing's looking at the new one:
        $this->setFolder($targetFolder);

        if ($this->moveLogs)
        {
            // Log is *next* to Item, so we move it to parent folder of $targetFolder:
            return $this->logger->moveLogfile(dirname($targetFolder));
        }

        return true;
    }


    /**
     * Returns the logger object used in this class.
     */
    public function getLogger()
    {
        return $this->logger;
    }


    /**
     * Turn #moveLogs on or off.
     */
    public function setMoveLogs($status)
    {
        $this->moveLogs = $status;
    }

    /**
     * Returns the status of #moveLogs.
     */
    public function getMoveLogs()
    {
        return $this->moveLogs;
    }


    /**
     * Set parent CIItem object of that this folder belongs to.
     */
    public function setParentItem(&$item)
    {
        if (! $item instanceof CIItem)
        {
            throw new Exception(_("Parent item given is not instance of 'CIItem'."));
        }
        $this->parentItem = $item;
    }

    public function getParentItem()
    {
        return $this->parentItem;
    }


    /**
     * Set parent CIFolder object of that this folder is a subfolder of.
     */
    public function setParentFolder(&$folder)
    {
        $this->parentFolder = $folder;
    }

    public function getParentFolder()
    {
        return $this->parentFolder;
    }


    /**
     * Returns 'true' if this folder has a parent folder object set.
     * False if not.
     */
    public function hasParent()
    {
        if ($this->parentFolder instanceof CIFolder)
        {
            return true;
        }
        return false;
    }


    /**
     * Sets a base folder as reference to handle relative sub-dir names.
     */
    public function setBaseFolder($baseFolder)
    {
        if (empty($baseFolder))
        {
            throw new Exception(_("Empty base folder given."));
        }

        if (!is_dir($baseFolder))
        {
            throw new Exception(sprintf(_("Invalid base folder: '%s' is not a directory."), $baseFolder));
        }

        // Make sure the baseFolder ends with a trailing slash:
        $baseFolder .= (substr($baseFolder, -1) == DIRECTORY_SEPARATOR ? '' : DIRECTORY_SEPARATOR);
        $this->baseFolder = $baseFolder;
    }


    /**
     * Returns the folder used as reference for relative paths.
     */
    public function getBaseFolder()
    {
        // Remove trailing slash, if there is one:
        $baseFolder = rtrim($this->baseFolder, DIRECTORY_SEPARATOR);
        return $baseFolder;
    }


    /**
     * Returns configuration CIConfig object.
     */
    public function getConfig()
    {
        if (empty($this->config))
        {
            throw new Exception(_("Configuration object empty."));
        }

        return $this->config;
    }


    public function hasOwnConfig()
    {
        $folderConfig = $this->config->getConfigForSection($myDir);
        if (!empty($folderConfig) && is_array($folderConfig)) return true;
        return false;
    }


    /**
     * Returns config array for this folder.
     *
     * Configuration per folder as follows:
     * - folder :    If subfolder has own config it extends/overwrites DEFAULT configuration.
     * - DEFAULT :   applies to all subfolders.
     * - UNDEFINED : Used as config for folders that have no own. Config behavior identical as if these folders had their own (now) ;)
     */
    public function getConfigForFolder($arguments=null)
    {
        $l = $this->logger;
        $config = $this->config;
        $myDir = $this->getSubDir();

        $l->logInfo(sprintf(_("Constructing config for folder '%s'"), $myDir));

        $config->initPlaceholders();
        $defaultConfig = $config->getConfigForDefault();
        $undefinedConfig = $config->getConfigForUndefined();

        // Arguments for placeholders are resolved here:
        $folderConfig = $config->getConfigForSection($myDir, $arguments);

        if (empty($folderConfig))
        {
            // Use 'undefined' config for folders that have no own:
            $folderConfig = $undefinedConfig;
        }
        // This step combines "default" with folder's settings. Folder's settings overwrite defaults.
        $myConfig = array_merge($defaultConfig, $folderConfig);

        $l->logDebug(sprintf(_("Config for '%s' is:\n%s"), $myDir, print_r($myConfig, true)));

        // TODO: If myConfig is empty at this point, throw an exception? It should not happen, but...
        // TODO: Case-insensitive check for foldernames in INI file?

        return $myConfig;
    }


    /**
     * Returns the pathname + filename of this folder object,
     * but relative to its base folder.
     */
    public function getSubDir()
    {
        // - return path relative to baseFolder
        // return "." if path *is* baseFolder

        $l = $this->logger;

        if (empty($this->baseFolder))
        {
            throw new Exception(_("Cannot get subdir: No base folder defined."));
        }

        if (!is_dir($this->baseFolder))
        {
            throw new Exception(sprintf(_("Cannot get subdir: Invalid base folder '%s'."), $this->baseFolder));
        }

        $pathName = $this->getPathname();
        $baseFolder = $this->baseFolder;

        $subDir = Helper::getAsRelativePath($pathName, $baseFolder);

        return $subDir;
    }


    /**
     * Returns the pathname + filename of this folder object.
     */
    public function getPathname()
    {
        $folderObj = $this->folderObj;

        if (empty($folderObj))
        {
            throw new Exception(_("getPathname failed: folderObj empty."));
        }

        $name = $folderObj->getPathname();
        return $name;
    }


    /**
     * Returns the filename (basename) of this folder object.
     */
    public function getFilename()
    {
        $name = $this->folderObj->getFilename();
        return $name;
    }


    /**
     * Set where temporary files are to be stored in.
     *
     * Folder must already exist, be writable - and subfolders there must
     * follow naming conventions of Common Inbox, so data can be exchanged
     * in temporary files between different tasks - and Inbox runs.
     */
    public function setTempFolder($tempFolder)
    {
        $l = $this->logger;

        if (empty($tempFolder))
        {
            throw new Exception(_("CIFolder: Refusing to set temp folder to empty string."));
        }

        if (!is_dir($tempFolder))
        {
            throw new Exception(sprintf(_("CIFolder: Invalid temp folder given. '%s' is not a directory."), $tempFolder));
        }

        if (!is_writeable($tempFolder))
        {
            throw new Exception(sprintf(_("CIFolder: Invalid temp folder given. '%s' is not writable. Check access rights?"), $tempFolder));
        }

        $this->tempFolder = $tempFolder;

        return true;
    }


    /**
     * Returns the foldername to use for temporary files.
     */
    public function getTempFolder()
    {
        $tempFolder = $this->tempFolder;
        if (empty($tempFolder) || !is_dir($tempFolder))
        {
            return false;
        }
        return $tempFolder;
    }



    // ---------------------------------------------------------------

    /**
     * Returns the unmodified value of TARGET_FOLDER setting (placeholders resolved).
     * If it is not configured for this folder, it returns 'null'.
     */
    public function getTargetFolderRaw()
    {
        $targetFolderRaw = $this->config->get(self::CONF_TARGET_FOLDER);

        // Mark whether this folder has a target folder configured or not:
        $this->hasTargetFolder = !empty($targetFolderRaw);

        return $targetFolderRaw;
    }


    /**
     * Checks if the configured target path is absolute or relative.
     *
     * Returns 'true' if absolute, 'false' if relative and 'NULL' if no target path is configured.
     */
    public function isTargetAbsolute()
    {
        $targetFolderRaw = $this->getTargetFolderRaw();

        switch ($this->hasTargetFolder)
        {
            case false:
                // No target folder configured.
                return null;
                break;

            case true:
                return Helper::isAbsolutePath($targetFolderRaw);

            default:
                throw new Exception(_("Cannot query for absolute/relative target path: Property 'hasTargetFolder' has unknown/invalid value."));
        }
    }


    /**
     * Returns the actual target path for this folder.
     *
     * The ruleset is as follows:
     * - Folder has NO target folder set:
     *   Use basename of source sub-folder appended to parent's target folder.
     *
     * - Folder has target folder set:
     *   absolute path: use as-is.
     *   relative path: prepend parent's target folder.
     *
     * Parameter $tempMask:
     *   The parameter "$tempMask" is a printf-style format string which is applied
     *   to the basename folder on the CIFolder recursion levels where TARGET_FOLDER
     *   is set.
     *   This allows maintaining the subfolder-structure on the target, but in
     *   a temporary folder. Useful for handling errors that would occur during e.g.
     *   copying to target, so that an unfinished/erroneous task does not affect
     *   the actual target until it is successful.
     */
    public function getTargetFolder($tempMask=null)
    {
        $targetFolderRaw = $this->getTargetFolderRaw();

        if ($this->hasTargetFolder && $this->isTargetAbsolute())
        {
            // If path is configured and absolute, use as-is:
            // (apply tempMask, if set)
            return $this->getTargetFolderTemp($targetFolderRaw, $tempMask);
        }

        // For relative paths, we need infos from their parent.
        // If that parent is not set, we have an error:
        if (!$this->hasParent())
        {
            throw new Exception(sprintf(_("Target path (%s) for '%s' is relative, but no parent folder is set."), $targetFolderRaw, $this->getSubDir()));
        }
        // From now on, we can assume we have a parent here.
        $parent = $this->getParentFolder();

        // Prepend parent's target folder:
        $targetSubDir = $this->hasTargetFolder ? $targetFolderRaw : basename($this->getPathname());
        $targetFolder = $parent->getTargetFolder($tempMask) . DIRECTORY_SEPARATOR . $targetSubDir;

        // TODO: Check if there are any uncomfortable errors that could happen here that we forgot to catch?

        return $targetFolder;
    }


    /**
     * Returns the temporary target folder path where the data is initially
     * copied, *before* validation (hashcodes, etc).
     */
    public function getTargetFolderTemp($targetFolder, $tempMask)
    {
        if (empty($targetFolder)) return false;         // TODO: exception?
        if (is_null($tempMask)) return $targetFolder;
        if (empty($tempMask)) return false;             // tODO: exception?

        $targetDir = dirname($targetFolder);
        $targetBase = basename($targetFolder);

        // Apply $tempMask to last foldername, and then put things back together:
        $targetTemp = sprintf($tempMask, $targetBase);
        $targetFolderTemp = $targetDir . DIRECTORY_SEPARATOR . $targetTemp;

        return $targetFolderTemp;
    }


    /**
     * Writes a message in the logs that contains a greppable/parseable string
     * containing the resolved target folder.
     *
     * $hasOwn = false:     Only log if this folder has TARGET_FOLDER set in config file.
     * $isAbsolute = true:  Only log if the TARGET_FOLDER is an absolute path.
     *
     * @return bool     success     True if line was logged, false if not (This is not an error).
     */
    public function logTargetFolder($hasOwn=false, $isAbsolute=true)
    {
        $l = $this->logger;

        // Only log the target folder if this CIFolder has one set.
        // Used to skip output of inherited- or sub-folder names.
        if ($hasOwn && !$this->hasTargetFolder) return false;
        // Don't log relative paths:
        if ($isAbsolute && !$this->isTargetAbsolute()) return false;

        $l->logPlain(sprintf(_("%s='%s'\n"), self::CONF_TARGET_FOLDER, $this->getTargetFolder()));
        return true;
    }


    /**
     * Return the absolute filename of the change-log directory listing.
     *
     * By default, the changelog file is located in the temp folder and
     * its filename is the basename of its corresponding CIFolder.
     */
    public function getChangelogFile()
    {
        $pathname = $this->getTempFolder();

        if (empty($pathname) || !is_dir($pathname)) throw new Exception(_("Cannot set changelog file: temp folder not valid/set?"));
        $changelog = $pathname . DIRECTORY_SEPARATOR . static::CHANGELOG_FILE;

        return $changelog;
    }

}

?>
