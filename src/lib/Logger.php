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

use ArchiveItem;
use FileCV;

#require_once('include/ArchiveItem.php');
#require_once('include/FileCV.php');

/**
 * Handles logging messages to different outputs, such as screen
 * or files. It supports adding a preformatted timestamp, as well as the messages'
 * loglevel to the output.
 *
 * Additionally it offers a few handy ways of formatting the text output,
 * such as adding a horizontal line, etc.
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
class Logger
{

    /* ========================================
     * CONSTANTS:
     * ======================================= */

    // Log levels:
    const LEVEL_NONE = 0;
    const LEVEL_DEBUG = 1;
    const LEVEL_INFO = 2;
    const LEVEL_NORMAL = 3;
    const LEVEL_WARNING = 5;
    const LEVEL_ALWAYS = 10;
    const LEVEL_ERROR = 20;

    // Output targets:
    const OUT_SCREEN = 1;
    const OUT_TEXTFILE = 2;
    // Array indices of output target options:
    const OUT_OPT_LOGLEVEL = 0;
    const OUT_OPT_PREFIXES = 1;
    const OUT_OPT_TIMESTAMPS = 2;
    const OUT_OPT_INSTANCEID = 3;

    // NOTE: If you add or change these values, don't forget to update
    //       the --help text in 'cinbox.php'.
    // Output formats:
    const OUT_STYLE_CLASSIC = 0;
    const OUT_STYLE_FILECV = 1;

    // Output formatting:
    const HEADLINE_CHAR1 = '=';
    const HEADLINE_CHAR2 = '-';
    const HEADLINE_CHAR3 = '+';

    const HEADLINE_WIDTH1 = 60;


    /* ========================================
     * PROPERTIES:
     * ======================================= */

    public static $TIMESTAMP_FORMAT = 'Y-m-d\TH:i:s';

    public $instanceId;
    private static $logLevels = array(
            self::LEVEL_ERROR,
            self::LEVEL_ALWAYS,
            self::LEVEL_WARNING,
            self::LEVEL_NORMAL,
            self::LEVEL_INFO,
            self::LEVEL_DEBUG
            );

    private static $logStyles = array(
            'classic' => self::OUT_STYLE_CLASSIC,
            'cv' => self::OUT_STYLE_FILECV
            );

    private $msgBuffer;
    private $msgOutput;
    private $logfile = null;

    private $showTimestamp = false;
    private $showInstanceId = false;

    private $useFileCV = false;
    private $cv;



    /* ========================================
     * METHODS:
     * ======================================= */

    function __construct()
    {
        // Generate unique ID for this object, in order to identify it:
        $this->instanceId = spl_object_hash($this);

        // By default, log everything to file but only "Normal" to screen:
        $this->addOutputOption(self::OUT_SCREEN, self::LEVEL_NORMAL, $this->getPrefixScreen(), false, false);
        $this->addOutputOption(self::OUT_TEXTFILE, self::LEVEL_INFO, $this->getPrefixMixed(), true, false);

        if ($this->useFileCV)
        {
          // Instantiate a file CV (log).
          $this->cv = new FileCV();
        }
    }


    /**
     * Checks if the given $logstyle is a valid option.
     * You can also use validateOutputFormat(), if you use Exception handling.
     *
     * @see $logStyles
     * @see validateOutputFormat()
     * @return True if valid, False if not.
     */
    public static function isValidOutputFormat($logstyle)
    {
        return array_key_exists($logstyle, self::$logStyles) ? true : false;
    }


    /**
     * Similar to isValidOutputFormat(), but already throws an exception
     * including an error message.
     *
     * @throws DomainException If $logstyle is not a valid output format option.
     * @see $logStyles
     * @see isValidOutputFormat()
     */
    public static function validateOutputFormat($logstyle)
    {
        if (!self::isValidOutputFormat($logstyle))
        {
            throw new \DomainException(sprintf(
                _("Invalid logstyle: '%s'. Valid options are: %s"),
                $logstyle,
                implode(', ', array_keys(self::$logStyles))
                ));
        }

        return true;
    }


    /**
     * Change output format.
     * Current options for $logstyle:
     *   - 0: classic
     *   - 1: fileCV
     *
     * @see $logStyles
     */
    public function setOutputFormat($logstyle)
    {
        $logstyleNum = self::$logStyles[$logstyle];

        switch ($logstyleNum)
        {
            case self::OUT_STYLE_FILECV:
                $this->useFileCV = true;
                break;
            default:
                // Defaults to "classic".
                $this->useFileCV = false;
                break;
        }

        if ($this->useFileCV && !$this->cv)
        {
          // Instantiate a file CV (log).
          $this->cv = new FileCV();
        }
     }


    /**
     * Configure formatting of an output option.
     */
    public function addOutputOption($output, $logLevel, $prefixes, $showTimestamp=false, $showInstanceId=false)
    {
        $this->msgOutput[$output] = array(
                self::OUT_OPT_LOGLEVEL => $logLevel,
                self::OUT_OPT_PREFIXES => $prefixes,
                self::OUT_OPT_TIMESTAMPS => $showTimestamp,
                self::OUT_OPT_INSTANCEID => $showInstanceId,
                );
    }


    public function setOutputOption($output, $option, $value)
    {
        $this->msgOutput[$output][$option] = $value;
    }


    /**
     * Sets short prefixes (=1 letter) for each type of log message.
     */
    public function getPrefixShort()
    {
        $prefixes = array(
                self::LEVEL_ERROR => 'E',
                self::LEVEL_ALWAYS => 'A',
                self::LEVEL_WARNING => 'W',
                self::LEVEL_NORMAL => 'N',
                self::LEVEL_INFO => 'I',
                self::LEVEL_DEBUG => 'D',
                );

        return $prefixes;
    }


    /**
     * Sets long prefixes (=1 word) for each type of log message.
     */
    public function getPrefixLong()
    {
        $prefixes = array(
                self::LEVEL_ERROR => 'ERROR',
                self::LEVEL_ALWAYS => 'ALWAYS',
                self::LEVEL_WARNING => 'WARNING',
                self::LEVEL_NORMAL => 'NORMAL',
                self::LEVEL_INFO => 'INFO',
                self::LEVEL_DEBUG => 'DEBUG',
                );

        return $prefixes;
    }


    /**
     * Screen output does not prefix all messages, so it's easier to read.
     * It still prefixes important / irregular messages though, such as errors/warning and debug messages.
     */
    public function getPrefixScreen()
    {
        $prefixes = array(
                self::LEVEL_ERROR => 'ERROR',
                self::LEVEL_ALWAYS => '',
                self::LEVEL_WARNING => 'WARNING',
                self::LEVEL_NORMAL => '',
                self::LEVEL_INFO => '',
                self::LEVEL_DEBUG => 'DEBUG',
                );

        return $prefixes;
    }


    /**
     * Similar to getPrefixScreen(), but keeps the 1-letter abbreviations
     * for non-error/warning messages.
     * Makes it easier for operators to spot problems when reading the logs.
     */
    public function getPrefixMixed()
    {
        $prefixes = array(
                self::LEVEL_ERROR => 'ERROR',
                self::LEVEL_ALWAYS => 'A',
                self::LEVEL_WARNING => 'WARNING',
                self::LEVEL_NORMAL => 'N',
                self::LEVEL_INFO => 'I',
                self::LEVEL_DEBUG => 'D',
                );

        return $prefixes;
    }


    public function setLogfile($filename)
    {
        if (empty($filename)) return false;
        if (!is_string($filename))
        {
            throw new \InvalidArgumentException(sprintf(_("Invalid logfile name: Not a string: %s"), print_r($filename, true)));
        }

        if (!file_exists($filename)) touch($filename);
        if (!is_writeable($filename))
        {
            throw new \RuntimeException(sprintf(_("Logfile '%s' is not writable. Check permissions?"), $filename));
        }

        $this->logfile = $filename;
        return true;
    }


    /**
     * Gives the name of the logfile (path + filename) as string.
     * If filename is empty (or not set yet), it returns False.
     *
     * @return string if set, False if not (or empty).
     * @retval $filename    Name of the logfile (path+filename).
     */
    public function getLogfile()
    {
        if (!empty($this->logfile)) return $this->logfile;
        return false;
    }


    /**
     * Used to set the boolean flag whether to show the timestamp in log messages.
     *
     * @param boolean   flag    Turn timestamp on/off (true  = show timestamp, false = hide timestamp).
     * @see $showTimestamp
     */
    public function showTimestamp($flag)
    {
        $this->showTimestamp = $flag;
    }


    /**
     * Used to set the boolean flag whether to show the instance ID of
     * the logger class object in log messages.
     *
     * The instance ID can be used to identify each Logger object instance and
     * is useful to debug which Logger object was used where.
     *
     * @param boolean   flag    Turn instance ID on/off (true = show instance ID, false = hide instance ID).
     * @see $showInstanceId
     */
    public function showInstanceId($flag)
    {
        $this->showInstanceId = $flag;
    }


    /**
     * Puts a log message on the internal message buffer.
     * It does NOT output anything. It only stores the message for being
     * output later on by functions like outputMsgBuffer().
     *
     * This is the main routine for logging messages.
     *
     * @see $msgBuffer
     * @see outputMsgBuffer()
     */
    public function addMessage($logLevel, $message, $source=null, $lineNumber=null)
    {
        $timestamp = date(self::$TIMESTAMP_FORMAT);

        $this->msgBuffer[] = array($logLevel, $timestamp, $message, $source, $lineNumber);
        $this->outputMsgBuffer();
    }


    /**
     * Outputs the current message buffer to different output formats (screen, file, etc).
     * The message buffer is cleared afterwards.
     *
     * @see $msgOutput
     * @see showTimestamp()
     * @see showInstanceId()
     */
    public function outputMsgBuffer()
    {
        // === Textfile:
        list ($logLevel, $prefixes, $showTimestamp, $showInstanceId) = $this->msgOutput[self::OUT_TEXTFILE];
        if ($logLevel != null)
        {
            if ($this->useFileCV)
            {
                // Use the new FileCV format.
                // Init the dummy archive item as a directory item with the
                // directory of the set log path (should be the item's
                // directory), so the log should end up in the right place.
                if ($this->logfile && count($this->msgBuffer))
                {
                    // Derive an item directory from the log file path.
                    $itemdir = $this->logfile;
                    if (!is_dir($itemdir))
                    {
                      $itemdir = preg_replace('/\.log$/', '', $itemdir);
                    }
                    if (!is_dir($itemdir))
                    {
                      $itemdir = dirname($itemdir);
                    }

                    // FIXME: Possibly "unclean" way deriving the item-id, assuming it to be the basename():
                    $archive_item = new ArchiveItem('directory', basename($itemdir));

                    // Putting anything into the item dir actually makes the item not be processed,
                    // so override logfile path and use what Logger traditionally uses.
                    $this->cv->setOverrideLogfile($this->logfile);
                    $log_type = FileCV::LOG_ENTRY_TYPE_ADD; // CInbox always adds.
                    foreach ($this->msgBuffer as $entry)
                    {
                        list ($eLogLevel, $timestamp, $message, $source, $lineNumber) = $entry;
                        switch ($eLogLevel)
                        {
                            case self::LEVEL_ERROR:
                                $log_level = FileCV::LOG_LEVEL_ERROR;
                                break;
                            case self::LEVEL_ALWAYS:
                                $log_level = FileCV::LOG_LEVEL_ALWAYS;
                                break;
                            case self::LEVEL_WARNING:
                                $log_level = FileCV::LOG_LEVEL_WARNING;
                                break;
                            case self::LEVEL_NORMAL:
                                $log_level = FileCV::LOG_LEVEL_NORMAL;
                                break;
                            case self::LEVEL_INFO:
                                $log_level = FileCV::LOG_LEVEL_INFO;
                                break;
                            case self::LEVEL_DEBUG:
                                $log_level = FileCV::LOG_LEVEL_DEBUG;
                                break;
                            default:
                                $log_level = FileCV::LOG_LEVEL_NORMAL;
                                break;
                        }
                        // Only write to file if there is a message
                        // and its log level is at least the target level.
                        if (strlen($message) && ($eLogLevel >= $logLevel))
                        {
                            $this->cv->log($archive_item, 'auto-cinbox', $log_type, $log_level, $message);
                        }
                    }
                }
            }
            else
            {
                // Use the old traditional CInbox Logger format.
                $this->showTimestamp($showTimestamp);
                $this->showInstanceId($showInstanceId);
                $lines = $this->formatAsText($this->msgBuffer, $logLevel, $prefixes);
                if (!empty($lines) && !empty($this->logfile)) $this->writeToLogfile($lines);
            }
        }

        // === Screen:
        list ($logLevel, $prefixes, $showTimestamp, $showInstanceId) = $this->msgOutput[self::OUT_SCREEN];
        if ($logLevel != null)
        {
            $this->showTimestamp($showTimestamp);
            $this->showInstanceId($showInstanceId);
            $lines = $this->formatAsText($this->msgBuffer, $logLevel, $prefixes);
            if (!empty($lines)) $this->printToScreen($lines);
        }

        unset($this->msgBuffer);
    }


    /**
     * Simply prints the contents of an array of text-lines to the screen.
     */
    public function printToScreen($lines)
    {
        if (!is_array($lines)) return false;

        foreach ($lines as $line)
        {
            echo $line;
        }

        return true;
    }


    /**
     * Writes log buffer to a file.
     */
    public function writeToLogfile($lines)
    {
        if (empty($this->logfile)) return false;

        if (file_put_contents($this->logfile, $lines, FILE_APPEND) === false)
        {
            throw new \RuntimeException(sprintf(_("Could not write to logfile '%s'."), $this->logfile));
        }
        return true;
    }


    /**
     * Formats current contents of the message buffer as text and returns it as an array of lines.
     */
    public function formatAsText($msgBuffer, $targetlogLevel, $prefixes)
    {
        $output = null;

        foreach ($msgBuffer as $entry)
        {
            list ($logLevel, $timestamp, $message, $source, $lineNumber) = $entry;
            if ($logLevel < $targetlogLevel) continue;

            // If message is empty, make a clean newline (no timestamp, etc):
            if (empty($message))
            {
                $output[] = "\n";
                continue;
            }

            if (!is_null($source) && !is_null($lineNumber))
            {
                $message .= sprintf("[%s:%s]", $source, $lineNumber);
            }

            // Make sure that *every line* of $message gets prefixed with loglevel, timestamp, etc.
            // Necessary for multi-line debug outputs!
            $line = strtok($message, "\r\n");
            while ($line !== false)
            {
                if ($this->showTimestamp)
                {
                    if ($this->showInstanceId)
                    {
                        $output_line = sprintf("(%s) [%s] %s  %s", $this->instanceId, $timestamp, $prefixes[$logLevel], $line);
                    }
                    else
                    {
                        $output_line = sprintf("[%s] %s  %s", $timestamp, $prefixes[$logLevel], $line);
                    }
                }
                else
                {
                    $output_line = sprintf("%s  %s", $prefixes[$logLevel], $line);
                }

                // Clean output line before adding:
                $output[] = trim($output_line) . "\n";
                $line = strtok("\r\n");
            }
        }

        return $output;
    }


    public function logAlways($msg)
    {
        $this->addMessage(self::LEVEL_ALWAYS, $msg);
    }


    public function logError($msg)
    {
        $this->addMessage(self::LEVEL_ERROR, $msg);
    }


    public function logWarning($msg)
    {
        $this->addMessage(self::LEVEL_WARNING, $msg);
    }


    public function logMsg($msg)
    {
        $this->addMessage(self::LEVEL_NORMAL, $msg);
    }


    public function logInfo($msg)
    {
        $this->addMessage(self::LEVEL_INFO, $msg);
    }


    public function logDebug($msg)
    {
        $this->addMessage(self::LEVEL_DEBUG, $msg);
    }


    /**
     * Writes the message to the logfile, without any headers, prefix or anything.
     * Just plain, as-is.
     * Currently, it's identical to calling "writeToLogFile()".
     */
    public function logPlain($msg)
    {
        return $this->writeToLogfile($msg);
    }


    /**
     * Logs the given exception $e as error message.
     */
    public function logException($msg, $e)
    {
        $this->logError($msg);
        $this->logError($e->getMessage());
        $this->logErrorPhp();
        $this->logDebug(sprintf(_("Exception was in '%s'(%d)"), $e->getFile(), $e->getLine()));
        $this->logDebug(sprintf(_("Stack trace: %s"), $e->getTraceAsString()));
    }


    /**
     * Logs error message, but adds information from PHP's internal
     * error handler.
     *
     * @See error_get_last()
     */
    public function logErrorPhp($msg=null)
    {
        $phpError = error_get_last();

        if (empty($phpError) || !is_array($phpError))
        {
            if (!empty($msg)) $this->logError($msg);
            return false;
        }

        $this->addMessage(
                self::LEVEL_ERROR,
                $msg . "\n" . $phpError['message'],
                $phpError['file'],
                $phpError['line']
                );

        return true;
    }


    /**
     * Write an empty line into the logs.
     * Used for visual spacing between entries.
     */
    public function logNewline($lines=1)
    {
        for ($line=1; $line<=$lines; $line++)
        {
            $this->logAlways("");
        }
    }


    /**
     * Logs a pre-formatted block that makes it possible to indicate
     * a new logging session.
     */
    public function logHeader($msg)
    {
        $this->logNewline(2);
        $this->logAlways($this->getHeadline(self::HEADLINE_CHAR1));
        $this->logAlways($msg);
        $this->logAlways($this->getTimestamp());
        $this->logAlways($this->getHeadline(self::HEADLINE_CHAR1));
    }


    /**
     * Returns a horizontal line consisting of '=' characters.
     * Can be used for markdown-style headlines.
     */
    public function getHeadline($char=self::HEADLINE_CHAR1, $width=self::HEADLINE_WIDTH1)
    {
        $line = '';
        for ($count = 1; $count <= $width; $count++) $line .= $char;
        return $line;
    }


    /**
     * Returns a pre-formatted timestamp string.
     * Default format is self::TIMESTAMP_FORMAT.
     */
    public function getTimestamp($format=null)
    {
        if (empty($format)) $format = self::$TIMESTAMP_FORMAT;
        return date($format);
    }


    public function setLogLevel($output, $logLevel)
    {
        if (!is_numeric($logLevel)) throw new \InvalidArgumentException(sprintf(_("Loglevel must be numeric, but is: %s"), gettype($logLevel)));
        if (!in_array($logLevel, static::$logLevels))
        {
            throw new \DomainException(sprintf(_("Invalid loglevel: %d"), $logLevel));
        }

        $this->setOutputOption($output, self::OUT_OPT_LOGLEVEL, $logLevel);
        return true;
    }


    /**
     * Relocate the logfile to a new folder or change its name.
     *
     * @param $folder    The target folder to move the logfile to.
     * @param $filename  The target filename to rename the logfile to.
     *
     * You can set either $folder or $filename - or both.
     * But you have to set at least one of them.
     */
    public function moveLogfile($folder=null, $filename=null)
    {
        $source = $this->logfile;

        if (empty($source)) throw new \Exception(_("Cannot move logfile: No logfile name set yet"));

        if (is_null($folder)) $folder = dirname($source);
        if (is_null($filename)) $filename = basename($source);

        $target = $folder . DIRECTORY_SEPARATOR . $filename;

        // If $target equals $source, then there's nothing to do:
        if (strcmp($source, $target) == 0)
        {
            return true;
        }

        if (file_exists($target))
        {
            throw new \Exception(sprintf(_("Cannot move logfile: target already exists: '%s'"), $target));
        }

        // Only move the logfile if it already exists.
        if (file_exists($source) && !rename($source, $target))
        {
            throw new \Exception(sprintf(_("Could not move logfile '%s' to '%s'."), $source, $target));
        }

        $this->logfile = $target;

        return true;
    }



}
