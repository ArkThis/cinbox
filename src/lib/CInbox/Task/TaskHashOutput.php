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
 * Writes the generated source-hashes to target.
 * Format and location can be set in config file.
 *
 *
 * @author Peter Bubestinger-Steindl (cinbox (at) ArkThis.com)
 * @copyright
 *  Copyright 2025 ArkThis AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.ArkThis.com/products/cinbox/">CInbox product website</a>
 *  - <a href="http://www.ArkThis.com/">ArkThis AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
class TaskHashOutput extends TaskHash
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    // Task name/label:
    const TASK_LABEL = 'Write hash output';

    // Names of config settings used by a task must be defined here.
    const CONF_HASH_OUTPUT = 'HASH_OUTPUT';             // Where to store hashcode output files.
    const CONF_HASH_FILENAME = 'HASH_FILENAME';         // Naming of file(s) to store hashcodes in. Different behavior depending on HASH_OUTPUT.
    const CONF_HASH_FILEFORMAT = 'HASH_FILEFORMAT';     // Format to write the hashcode textfile as.

    // Valid options for settings.
    // Options for "CONF_HASH_OUTPUT":
    const OPT_OUTPUT_FILE = 'file';                     // One hashcode file per file
    const OPT_OUTPUT_FOLDER = 'folder';                 // One hashcode file per folder
    // Options for "CONF_HASH_FILEFORMAT":
    const OPT_HASH_FORMAT_GNU = 'gnu';                       // GNU md5sums style (unix linebreaks)
    const OPT_HASH_FORMAT_WIN = 'win';                       // GNU md5sums style (windows linebreaks)
    const OPT_HASH_FORMAT_MACOS = 'macos';                   // MacOS md5sums style



    /* ========================================
     * PROPERTIES 
     * ======================================= */

    protected $hashOutput;                              // Contains value of CONF_HASH_OUTPUT
    protected $hashFilename;                            // Contains value of CONF_HASH_FILENAME
    protected $hashFileformat;                          // Contains value of CONF_HASH_FILEFORMAT

    public static $OPT_HASH_OUTPUTS = array(
            self::OPT_OUTPUT_FILE,
            self::OPT_OUTPUT_FOLDER,
            );

    public static $OPT_HASH_FILEFORMATS = array(
            self::OPT_HASH_FORMAT_GNU => "[@HASHCODE@]  [@FILENAME@]\n",
            self::OPT_HASH_FORMAT_WIN => "[@HASHCODE@]  [@FILENAME@]\r\n",
            self::OPT_HASH_FORMAT_MACOS => "[@FILENAME@]  [@HASHCODE@]\n",
            );



    /* ========================================
     * METHODS 
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);
    }



    /**
     * Prepare everything so it's ready for processing.
     * @return bool     success     True if init went fine, False if an error occurred.
     */
    public function init()
    {
        if (!parent::init()) return false;

        $l = $this->logger;

        // This task is optional, so we return happily unless all options are set:
        if (empty($this->hashOutput) || empty($this->hashFilename) || empty($this->hashFileformat))
        {
            $l->logInfo(sprintf(
                        _("Skipping folder '%s': Not all options set for task %s. That's okay."),
                        $this->CIFolder->getSubDir(),
                        $this->name));
            $this->setStatusDone();
            return false;
        }

        // Check if we have a proper temp folder:
        if (!$this->checkTempFolder()) return false;

        // Must return true on success:
        return true;
    }


    /**
     * Perform the actual steps of this task.
     */
    public function run()
    {
        if (!parent::run()) return false;

        $l = $this->logger;

        $sourceFolder = $this->sourceFolder;
        $targetFolder = $this->targetFolder;

        $l->logMsg(sprintf(_("Folder '%s'..."), $targetFolder));
        $hashCodes = $this->getTempHashForFolder($sourceFolder);
        if (!is_array($hashCodes)) 
        {
            $this->setStatusPBCT();
            return false;
        }

        // Sort hashcodes by filename:
        if (!ksort($hashCodes))
        {
            $l->logWarning(_("Could not sort hashcodes by filename. This is weird."));
        }

        if (!$this->writeHashCodeFiles($hashCodes, $targetFolder))
        {
            $this->setStatusPBCT();
            return false;
        }

        // Must return true on success:
        $this->setStatusDone();
        return true;
    }


    /**
     * Load settings from config that are relevant for this task.
     */
    protected function loadSettings()
    {
        if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        // -------
        $this->hashOutput = strtolower($config->get(self::CONF_HASH_OUTPUT));
        $l->logDebug(sprintf(_("Hashcode output mode: %s"), $this->hashOutput));
        if (!empty($this->hashOutput) && !$this->isValidHashOutput($this->hashOutput))
        {
            $l->logError(sprintf(_("Invalid value set for %s: '%s' (Valid options are: %s)"),
                        self::CONF_HASH_OUTPUT,
                        $this->hashOutput,
                        implode(', ', self::$OPT_HASH_OUTPUTS)));
            $this->setStatusConfigError();
            return false;
        }

        // -------
        $this->hashFilename = $config->get(self::CONF_HASH_FILENAME);
        $l->logDebug(sprintf(_("Hashcode output filename mask: %s"), $this->hashFilename));

        // -------
        $this->hashFileformat= strtolower($config->get(self::CONF_HASH_FILEFORMAT));
        $l->logDebug(sprintf(_("Hashcode output format: %s"), $this->hashFileformat));
        if (!empty($this->hashFileformat) && !$this->isValidHashFileformat($this->hashFileformat))
        {
            $l->logError(sprintf(_("Invalid value set for %s: '%s' (Valid options are: %s)"),
                        self::CONF_HASH_FILEFORMAT,
                        $this->hashFileformat,
                        implode(', ', self::$OPT_HASH_FILEFORMATS)));
            $this->setStatusConfigError();
            return false;
        }


        // Must return true on success:
        return true;
    }



    // --------------------------------------------
    // Task-specific methods
    // --------------------------------------------

    /**
     * Checks if the given mode is a valid option for CONF_HASH_OUTPUT.
     * Returns 'true' if it is valid - 'false' if not.
     *
     * The check is case-sensitive, so you might want to normalize case before calling this.
     */
    protected function isValidHashOutput($option)
    {
        return in_array($option, static::$OPT_HASH_OUTPUTS);
    }


    /**
     * Checks if the given mode is a valid option for CONF_HASH_FILEFORMAT.
     * Returns 'true' if it is valid - 'false' if not.
     *
     * The check is case-sensitive, so you might want to normalize case before calling this.
     */
    protected function isValidHashFileformat($option)
    {
        return array_key_exists($option, static::$OPT_HASH_FILEFORMATS);
    }


    /**
     * Returns one line for a hashcode output format, according to
     * the format defined by $hashFormat.
     */
    protected function formatHash($outputMask, $hashType, $hashCode, $filename)
    {
        $l = $this->logger;

        $arguments = array(
                __HASHTYPE__ => $hashType,
                __HASHCODE__ => $hashCode,
                __FILENAME__ => basename($filename),
                );

        $outputLine = Helper::resolveString($outputMask, $arguments);

        return $outputLine;
    }


    /**
     * Iterates through array $hashCodes and assembles each line for the 
     * output hash textfile, according to self::$OPT_HASH_FILEFORMATS.
     *
     * Depending on the CONF_HASH_OUTPUT mode, the lines are written to the corresponding 
     * files on the target.
     *
     * Existing hashcode files with the same name as defined by CONF_HASH_FILENAME will
     * a) be overwritten (CONF_HASH_OUTPUT = file)
     * b) be merged with the new hashcode lines (CONF_HASH_OUTPUT = folder)
     *    Please make sure that the hashcode format is identical in this case,
     *    because otherwise the resulting files will not work for automatic validation
     *    using tools like md5sum, etc.
     */
    protected function writeHashCodeFiles($hashCodes, $targetFolder)
    {
        $l = $this->logger;

        if (!is_array($hashCodes)) throw new Exception(_("writeHashCodeFiles: parameter hashCodes must be an array."));

        // This filename will be used for mode OPT_OUTPUT_FOLDER.
        // It is assembled here to filter out already existing targetHashFiles in the output lines:
        $targetHashFile = $targetFolder . DIRECTORY_SEPARATOR . sprintf($this->hashFilename, basename($targetFolder));

        $outputLines = array();
        $errors = 0;
        foreach ($hashCodes as $sourceFile=>$hashCode)
        {
            // Skip existing targetHashFiles:
            if (strcmp(basename($sourceFile), basename($targetHashFile)) == 0) continue;

            // Skip file if it matches 'CONF_COPY_EXCLUDE' patterns:
            $exclude = $this->exclude($sourceFile, $this->copyExclude);
            if ($exclude !== false)
            {
                $l->logMsg(sprintf(_("Excluding file '%s'. Matches pattern '%s'."), $sourceFile, $exclude));
                continue;
            }

            $outputMask = self::$OPT_HASH_FILEFORMATS[$this->hashFileformat];
            $outputLine = $this->formatHash($outputMask, $this->hashType, $hashCode, $sourceFile);
            $outputLines[] = $outputLine;

            // Linebreaks are embedded in format string. Remove them for debug output:
            $l->logDebug(sprintf(_("Output hash line: '%s'"), str_replace(array("\r","\n"), '', $outputLine)));

            // Write one hashfile per-file:
            if ($this->hashOutput == self::OPT_OUTPUT_FILE)
            {
                $targetHashFile = $targetFolder . DIRECTORY_SEPARATOR . sprintf($this->hashFilename, basename($sourceFile));
                $l->logInfo(sprintf(_("Target hash output: %s"), $targetHashFile));
                if (!$this->saveHashesToFile($targetHashFile, $outputLine, $overwrite=true)) 
                {
                    $errors++;
                    continue;
                }
            }
        }

        // Write one hashfile per-folder:
        if ($this->hashOutput == self::OPT_OUTPUT_FOLDER && !empty($outputLines))
        {
            $l->logInfo(sprintf(_("Target hash output: %s"), $targetHashFile));
            if (!$this->saveHashesToFile($targetHashFile, $outputLines, $overwrite=false)) return false;
        }

        if ($errors > 0) return false;
        return true;
    }


    /**
     * Writes formatted hashcode lines to file.
     * If file exists and $overwrite is false, then hashcode lines will
     * be appended.
     *
     * @See appendHashcodes
     */
    protected function saveHashesToFile($file, $contents, $overwrite=false)
    {
        $l = $this->logger;

        if (empty($contents))
        {
            $l->logError(sprintf(_("Empty contents given. Won't write to file '%s'."), $file));
            $this->setStatusPBCT();
            return false;
        }

        if (file_exists($file))
        {
            if (!is_writeable($file))
            {
                $l->logError(sprintf(_("Target hash file is not writable: '%s'. Check permissions?"), $file));
                $this->setStatusPBCT();
                return false;
            }

            if (!$overwrite)
            {
                $l->logMsg(sprintf(
                            _("Integrating new hashcodes into '%s/%s'..."),
                            basename(dirname($file)),
                            basename($file)));
                $contents = $this->appendHashcodes($file, $contents);
            }
        }

        if (!file_put_contents($file, $contents))
        {
            $l->logErrorPhp(sprintf(_("Could not write to '%s'."), $file));
            $this->setStatusPBCT();
            return false;
        }

        return true;
    }


    /**
     * Appends new hashcode lines to contents of an existing hashcode file.
     *
     * The format of the hashcode file should be identical, otherwise the
     * resulting file will be corrupt.
     * Duplicate entries are removed (Works only if format is identical).
     *
     * Returns the result as array of text-lines.
     */
    protected function appendHashcodes($targetHashFile, $new)
    {
        $lines = file($targetHashFile);
        $result = array_unique(array_merge($lines, $new));

        return $result;
    }



}

?>
