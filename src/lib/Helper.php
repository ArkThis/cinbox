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

namespace ArkThis;

use \Exception as Exception;
use \RecursiveIteratorIterator as RecursiveIteratorIterator;
use \RecursiveDirectoryIterator as RecursiveDirectoryIterator;
use \DirectoryIterator as DirectoryIterator;
use \SplFileInfo as SplFileInfo;


/**
 * This class contains functions that just don't fit anywhere else.
 * Or that are simply useful in many different places ;)
 *
 * Methods in here are preferred to be static, so they can be used without
 * requiring an instance of 'Helper'.
 *
 *
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
class Helper
{
    /* ========================================
     * CONSTANTS
     * ======================================= */



    /* ========================================
     * PROPERTIES:
     * ======================================= */



    /* ========================================
     * METHODS:
     * ======================================= */

    /**
     * Resolves a mask-string to its final value by replacing all placeholders
     * using the provided $arguments array containing a "placeholder=>value" mapping.
     */
    public static function resolveString($masked, $arguments)
    {
        $result = $masked;        // This is to provide meaningful variable names later on.

        if (empty($masked))
        {
            throw new Exception(_("Unable to resolve empty mask"));
        }

        if (empty($arguments))
        {
            throw new Exception(_("No arguments given to resolve string placeholders"));
        }

        if (!is_array($arguments))
        {
            throw new Exception(_("Parameter \$argument is not an array"));
        }

        foreach ($arguments as $placeholder=>$value)
        {
            // Obacht! This is a recursion:
            $result = str_replace(
                    $placeholder,
                    $value,
                    $result
                    );
        }

        return $result;
    }


    /**
     * Creates subfolders for the path of $fileName if they don't already exists and
     * tries to 'touch' the file.
     * Used to check before attempting to write to $fileName.
     */
    public static function touchFile($fileName)
    {
        // If not existing, create subfolders (with parent folders):
        $subDir = dirname($fileName);
        if (!file_exists($subDir))
        {
            if (!mkdir($subDir, 0777, true))
            {
                throw new Exception(sprintf(_("touchFile: Could not create subfolder: '%s'."), $subDir));
            }
        }

        return true;
    }


    /**
     * Checks if the given path name is an absolute or relative path,
     * by checking if it starts with a DIRECTORY_SEPARATOR character.
     * Returns 'true' if path is absolute, 'false' if relative.
     */
    public static function isAbsolutePath($pathName)
    {
        if (empty($pathName))
        {
            throw new Exception(_("isAbsolutePath: Empty pathname given."));
        }

        // If it begins with a '/' or '\', we assume it's absolute:
        if (strpos($pathName, DIRECTORY_SEPARATOR) === 0)
        {
            return true;
        }
        return false;
    }


    /**
     * Returns the name of the user the PHP process is running as.
     */
    public static function getPhpUser()
    {
        // FIXME: This might not work on Windows (or non-unixes):
        $username = exec('whoami');

        if (empty($username))
        {
            throw new Exception(_('Unable to determine PHP username.'));
        }

        return $username;
    }


    /**
     * Creates an array with all subfolders of $folderName.
     * The paths within the array are absolute.
     */
    public static function getSubFolderArray($folderName, $maxDepth=99, $depth=0)
    {
        if (!is_dir($folderName))
        {
            throw new Exception(sprintf(
                        _("Can't create subfolder array: Invalid folder '%s' (not a directory?)."),
                        $folderName));
        }

        if ($depth > $maxDepth)
        {
            throw new Exception(sprintf(
                        _("getSubFolderArray: Maximum recursion depth %d exceeded (%d)!"),
                        $maxDepth,
                        $depth));
        }

        // Include start folder:
        $result = array($folderName);

        $all = glob($folderName . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        asort($all);

        foreach($all as $entry)
        {
            $result = array_merge($result, self::getSubFolderArray($entry, $depth +1));
        }

        return $result;
    }


    /**
     * Recurse through a folder structure and remove all empty subfolders.
     * Returns 'true' if all folders were empty. 'false' if not.
     */
    public static function removeEmptySubfolders($folderName)
    {
        if (!file_exists($folderName)) return true;
        // TODO: Throw "InvalidArgumentException"?
        if (!is_dir($folderName)) throw new Exception(
            sprintf(
                _("removeEmptySubfolders: Invalid folder '%s' (not a directory?)."),
                $folderName));

        $all = glob($folderName . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        foreach($all as $entry)
        {
            $result = self::removeEmptySubfolders($entry);
            if (!$result)
            {
                throw new Exception(sprintf(
                    _("Could not remove folder '%s'."),
                    $entry));
            }
        }

        return @rmdir($folderName);
    }

    /**
     * Recurse through a folder structure and remove it - including subfolders.
     * Returns 'true' if the folder could be removed successfully, 'false' if not.
     *
     * (Based on code from: http://stackoverflow.com/posts/3352564/revisions)
     */
    public static function removeFolder($folderName)
    {
        $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folderName, RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
                );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getPathname());
        }
        return rmdir($folderName);
    }


    /**
     * Generates and returns the proper filename in the target folder
     * for the given source file.
     * Returns 'false' on error.
     */
    public static function getTargetFilename($sourceFile, $targetFolder)
    {
        $baseName = basename($sourceFile);
        $targetFile = $targetFolder . DIRECTORY_SEPARATOR . $baseName;

        if (!empty($targetFile)) return $targetFile;
        return false;
    }


    /**
     * Returns $pathName's path as relative to $baseFolder.
     *
     * $pathName can be a folder or filename, but the path must start with
     * $baseFolder in order to return a valid result.
     */
    public static function getAsRelativePath($pathName, $baseFolder)
    {
        // Remove trailing slashes to normalize input:
        $pathName = rtrim($pathName, DIRECTORY_SEPARATOR);
        $baseFolder = rtrim($baseFolder, DIRECTORY_SEPARATOR);

        // Folder path and base path are identical. So return '.' (to satisfy config lookups):
        if (strcmp($pathName, $baseFolder) == 0)
        {
            return '.';
        }

        // Add DIRECTORY_SEPARATOR to baseFolder. Otherwise $subDir would look like an absolute path.
        $subDir = str_replace($baseFolder . DIRECTORY_SEPARATOR, '', $pathName);

        return $subDir;
    }


    /**
     * Reads 2 textfiles into an array, appending contents of $file2
     * after contents of $file1.
     * Returns the result as one unified array.
     */
    public static function appendTextfiles($file1, $file2)
    {
        $lines = file($file1);
        $lines2 = file($file2);

        array_push($lines, $lines2);
        return $lines;
    }


    /**
     * Searches the youngest file/folder found in $folder.
     * Returns it as SplFileInfo object.
     */
    public static function getYoungest($folder)
    {
        $age = null;
        $youngest = null;

        if (Helper::isFolderEmpty($folder))
        {
            // If $folder is empty, return the folder itself:
            return (new SplFileInfo($folder));
        }

        $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
                );
        foreach ($files as $fileinfo) {
            $mtime = $fileinfo->getMTime();
            if (is_null($age)) $age = $mtime;
            if ($mtime >= $age)
            {
                $youngest = $fileinfo;
                $age = $mtime;
            }
            //printf("%s = %d: %s\n", date('Ymd-His', $mtime), (time() - $mtime) / 60, $fileinfo->getFilename()); // DEBUG
        }
        return $youngest;
    }


    /**
     * Checks if a folder is empty.
     *
     * \retval boolean
     *  Returns 'true' if folder is empty.
     *  'False' if not.
     */
    public static function isFolderEmpty($folder)
    {
        $iterator = new \FilesystemIterator($folder);
        return !$iterator->valid();
    }



    /**
     * Creates an unfiltered directory listing using #DirectoryIterator, but
     * returns the listing as array.
     * Dots ('.' and '..') are excluded from the list.
     *
     * The array contains the filenames as keys and the directory entries
     * as SplFileInfo objects as values.
     * Therefore, if you want to sort the list alphabetically, use PHP's
     * "ksort()" function.
     *
     * @retval Array
     *  An array of entries of $folder.
     *  key = SplFileInfo::getFilename()
     *  value = DirectoryIterator object (extends SplFileInfo)
     */
    public static function getFolderListing($folder)
    {
        if (empty($folder)) throw new \InvalidArgumentException(_("Foldername must not be empty"));

        $list = array();
        $di = new \DirectoryIterator($folder);
        foreach ($di as $entry) {
            // Skip '.' and '..':
            if ($entry->isDot()) continue;

            $list[$entry->getFilename()] = clone $entry;
        }
        return $list;
    }


    /**
     * Same functionality as getFolderListing(), but different output format!
     *
     * @retval Array
     *  An array of entries of $folder.
     *  key = Absolute path+filename
     *  value = Output of stat() for the file
     */
    public static function getFolderListing2($folder)
    {
        if (empty($folder)) throw new \InvalidArgumentException(_("Foldername must not be empty"));

        $list = array();
        $di = new DirectoryIterator($folder);
        foreach ($di as $entry) {
            // Skip '.' and '..':
            if ($entry->isDot()) continue;

            $pathname = $entry->getPathname();
            $list[$pathname] = self::getStats($pathname);
        }
        return $list;
    }


    /**
     * Similar to getFolderListing(), but recurses through subfolders.
     *
     * Supports some flags, borrowed from glob():
     *   GLOB_ONLYDIR: List only directories. No files.
     *
     * @retval Array
     *  An array of entries of $folder.
     *  key = SplFileInfo::getFilename()
     *  value = DirectoryIterator object (extends SplFileInfo)
     */
    public static function getRecursiveFolderListing($folder, $flags=null)
    {
        if (empty($folder)) throw new \InvalidArgumentException(_("Foldername must not be empty"));

        $onlyDirs = false;
        if (!empty($flags))
        {
            if ($flags & GLOB_ONLYDIR) $onlyDirs = true;
        }

        $di = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
                );

        $list = array();
        foreach ($di as $entry)
        {
            if ($onlyDirs && $entry->isFile()) continue;
            $list[$entry->getPathname()] = clone $entry;
        }
        return $list;
    }


    /**
     * Converts an array with filenames as keys into a simple plaintext
     * string listing.
     *
     * Can be used on the return arrays of getFolderListing() or getRecursiveFolderListing().
     *
     * @param $filter_keys [array]  List of keys to filter/remove from listing.
     */
    public static function dirlistToText($list, $filter_keys=null)
    {
        if (empty($list)) throw new \InvalidArgumentException(_("Directory listing must not be empty"));
        if (!is_array($list)) throw new \InvalidArgumentException(_("Directory listing must be an array"));

        // stat() outputs each value 2 times: with numeric and associative array index.
        // This array here is used to filter out only the associative ones for printing:
        $print_keys = array(
                    'dev', 'ino', 'mode', 'nlink', 'uid', 'gid', 'rdev',
                    'size', 'atime', 'mtime', 'ctime', 'blksize', 'blocks'
                    );

        if (!empty($filter_keys))
        {
            if (!is_array($filter_keys))
            {
                throw new \InvalidArgumentException(
                        sprintf(
                            _("filter_keys must be an array, but is of type %s"),
                            gettype($filter_keys)));
            }

            $print_keys = array_diff($print_keys, $filter_keys);
        }

        $print_keys = array_flip($print_keys);
        $textlist = null;
        foreach ($list as $filename)
        {
            // TODO: This should never happen, but if it does should we tell?
            // Not really necessary for a current listing as text though.
            if (!file_exists($filename)) continue;

            $stats = stat($filename);

            if ($stats === false || empty($stats))
            {
                throw new Exception(sprintf(
                            _("stat() call failed for %s"),
                            $filename));
            }

            $stats = array_intersect_key($stats, $print_keys);
            $line = sprintf(
                    "File: %s\nKeys: %s\nValues: %s\n\n",
                    $filename,
                    implode(';', array_keys($stats)),
                    implode(';', $stats));

            $textlist .= $line;
        }

        return $textlist;
    }


    /**
     * Compares 2 arrays by keys and returns the difference.
     *
     * It uses PHP's "array_diff_key()", but it properly returns the
     * difference even if the first array is empty.
     */
    public static function arrayDiffKey($array1, $array2)
    {
        if (empty($array1))
        {
            return array_diff_key($array2, $array1);
        }
        else
        {
            return array_diff_key($array1, $array2);
        }
    }


    /**
     * Wrapper to PHP's internal stat() function.
     * Throws an exception if stat() call was not successful.
     *
     * @throws Exception If stat() call was not successful.
     */
    public static function getStats($filename)
    {
        $stats = stat($filename);

        if ($stats === false || empty($stats) || !is_array($stats))
        {
            throw new Exception(sprintf(
                        _("stat() call failed for %s"),
                        $filename));
        }

        return $stats;
    }


    /**
     * Returns the basename of a file (without path).
     *
     * @param $filename     [string]    Regular filename string, with or without path.
     * @param $suffix       [bool]      Return filename without file suffix if 'false'.
     *
     * @retval boolean
     *  Returns 'true' if successful,
     *  'False' if an error occurred.
     */
    public static function getBasename($filename, $suffix=true)
    {
        $pathinfo = pathinfo($filename);

        return ( $suffix ? $pathinfo['basename'] : $pathinfo['filename'] );
    }


    /**
     * Resolves a given path relative to a given base, and returns the absolute
     * path (+filename if given).
     *
     * It's merely a call to realpath(), but works if the file doesn't exist
     * (yet).
     * BUT: The path must already exist!
     *
     * @param $filename     [string]    Path or path+filename.
     * @param $base         [string]    Folder to consider as base for relative $filename.
     */
    public static function getAbsoluteName($filename, $base=null)
    {
        if (!empty($base))
        {
            // We don't check if $filename is absolute or relative, and assume
            // that if $base is set, it's relative.
            $filename = $base . DIRECTORY_SEPARATOR . $filename;
        }

        $pathinfo = pathinfo($filename);

        // If it exists, we're happy to use realpath as-is:
        if (file_exists($filename)) return realpath($filename);

        $basename = $pathinfo['basename'];
        $dir = $pathinfo['dirname'];

        $realDir = realpath($dir);
        if ($realDir === false) throw new \RuntimeException(
                sprintf(_("Unable to resolve absolute path. Folder does not exist: '%s'"),
                    $dir
                    ));

        $absolute = $realDir . DIRECTORY_SEPARATOR . $basename;

        return $absolute;
    }



}

?>
