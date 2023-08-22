<?php
/* LICENSE. */

class FileCV {
  // File CV/Logging PHP class
  // Generic logging for media archive files.
  //

  const ARCHIVE_LOG_NAME = 'archive.log';
  const ITEM_LOG_SUFFIX = '.log';
  const ITEMLOG_DIR = 'HIRES';

  // default: one CV/log file per archive item
  const CV_TYPE_PER_ITEM = 0;
  // only one CV/log file for the whole archive
  const CV_TYPE_PER_ARCHIVE = 1;
  // one CV/log file per directory in the archive (TODO: NOT IMPLEMENTED YET!)
  const CV_TYPE_PER_DIRECTORY = 2;

  // Entry Types allowed from e.g. commandlines.
  // Code should actually use the constants below.
  const LOG_ENTRY_ALLOWED_TYPES = array('ADD','DEL','MOD','VFY','COR','DIV');
  const LOG_ENTRY_TYPE_ADD = 'ADD';
  const LOG_ENTRY_TYPE_DELETE = 'DEL';
  const LOG_ENTRY_TYPE_MODIFY = 'MOD';
  const LOG_ENTRY_TYPE_VERIFY = 'VFY';
  const LOG_ENTRY_TYPE_CORRECTION = 'COR';
  const LOG_ENTRY_TYPE_MISC = 'DIV';

  // Log levels with shorthand prefixes.
  const LOG_LEVEL_ALLOWED = array('E','A','W','N','I','D');
  const LOG_LEVEL_ERROR = 'E';
  const LOG_LEVEL_ALWAYS = 'A';
  const LOG_LEVEL_WARNING = 'W';
  const LOG_LEVEL_NORMAL = 'N';
  const LOG_LEVEL_INFO = 'I';
  const LOG_LEVEL_DEBUG = 'D';

  private $cv_type = self::CV_TYPE_PER_ITEM;
  private $override_logfile = false;
  private $escape_contents = true;
  private $csv_separator = ';';
  private $csv_textquote = '"';

  function __construct($cv_type = self::CV_TYPE_PER_ITEM) {
    $this->cv_type = $cv_type;
  }

  public function log($archive_item,
                      $operator_name = 'auto',
                      $entry_type = self::LOG_ENTRY_TYPE_MISC,
                      $log_level = self::LOG_LEVEL_NORMAL,
                      $comment = '') {
    if ($this->override_logfile) {
      $cv_file_path = $this->override_logfile;
    }
    else {
      $cv_file_path = $this->_get_file_path($archive_item);
    }
    if (!in_array($entry_type, self::LOG_ENTRY_ALLOWED_TYPES)) {
      throw new Exception('Log entry type not recognized: '.$entry_type);
    }
    if (!in_array($log_level, self::LOG_LEVEL_ALLOWED)) {
      throw new Exception('Log level not recognized: '.$log_level);
    }

    $date = new DateTime();
    // c is ISO 8601 date, e.g. 2004-02-12T15:19:21+00:00
    $time_formatted = $date->format('c');

    // If the original comment has multiple lines, wrap the CV-header
    // around each one:
    foreach (explode("\n", $comment) as $line) {
      $csv_fields = null;   // Avoid leftovers from previous iteration.
      // Only wrap CV-header around non-empty lines:
      if (!empty(trim($line))) {
      $csv_fields = array($time_formatted,
                          $archive_item->get_signature(),
                          $entry_type,
                          $log_level,
                          $operator_name,
                          $line);
      }
      $this->_cvfile_append_line($cv_file_path, $csv_fields);
    }
  }

  public function importlog($archive_item,
                            $filename,
                            $operator_name = 'import-log',
                            $entry_type = self::LOG_ENTRY_TYPE_MISC,
                            $log_level = self::LOG_LEVEL_NORMAL) {
    if ($this->override_logfile) {
      $cv_file_path = $this->override_logfile;
    }
    else {
      $cv_file_path = $this->_get_file_path($archive_item);
    }
    if (!file_exists($filename)) {
      throw new Exception('File to import was not found: '.$filename);
    }
    if (!in_array($entry_type, self::LOG_ENTRY_ALLOWED_TYPES)) {
      throw new Exception('Log entry type not recognized: '.$entry_type);
    }
    if (!in_array($log_level, self::LOG_LEVEL_ALLOWED)) {
      throw new Exception('Log level not recognized: '.$log_level);
    }

    $date = new DateTime();
    // c is ISO 8601 date, e.g. 2004-02-12T15:19:21+00:00
    $time_formatted = $date->format('c');
    $oldlines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    array_unshift($oldlines,
        '>>> Importing external log from '.basename($filename).':');
    array_push($oldlines,
        '<<< End of import from '.basename($filename));
    foreach ($oldlines as $line) {
      $csv_fields = array($time_formatted,
                          $archive_item->get_signature(),
                          $entry_type,
                          $log_level,
                          $operator_name,
                          $line);
      $this->_cvfile_append_line($cv_file_path, $csv_fields);
    }
  }

  /**
   * Set a log file path/name to override the default derived from the archive
   * item's info. Set to empty or false to use default again.
   */
  public function setOverrideLogfile($filepath) {
    if ($filepath && strlen($filepath)) {
      $this->override_logfile = $filepath;
    }
    else {
      $this->override_logfile = false;
    }
  }

  private function _get_file_path($archive_item) {
    switch ($this->cv_type) {
      case self::CV_TYPE_PER_ITEM:
        // Get per-item file path
        $mpath = $archive_item->get_dir('metadata');
        if (!is_dir($mpath)) {
          // We may be acting on a deleted item, see if there is a delme path.
          // If we find nothing, continue with the original, we'll fail later.
          $dirs = glob($archive_item->get_dir('metadata_delme_mask'));
          if ($dirs) {
            $mpath = array_shift($dirs); // first directory found
          }
        }
        $logname = ($archive_item->item_type == 'directory'
                    ? strtolower(basename($archive_item->get_signature()))
                    : strtolower($archive_item->get_signature()))
                   .self::ITEM_LOG_SUFFIX;
        return $mpath.ArchiveItem::addpath($logname);
        break;
      case self::CV_TYPE_PER_DIRECTORY:
        // Get per-directory file path
        // Not implemented, so fall through to per-archive default.
        //break;
      case self::CV_TYPE_PER_ARCHIVE:
      default:
        // Get per-archive file path
        return $archive_item->get_dir('DIR_ARCHIVE_ROOT')
               .ArchiveItem::addpath(self::ARCHIVE_LOG_NAME);
        break;
    }
  }

  private function _cvfile_append_line($filename, $csv_fields) {
    if (is_writable($filename) ||
        (!file_exists($filename) && is_writable(dirname($filename)))) {
      if (!$fhandle = fopen($filename, 'a')) {
        throw new Exception('Cannot open file: '.$filename);
      }
      $textline = '';

      // If csv_fields is empty, just write an empty line.
      // No CV-header necessary, because it's just an optical break ;)
      if (!empty($csv_fields)) {
        foreach ($csv_fields as $field) {
          if (strlen($textline)) { $textline.= $this->csv_separator; }
          if ($this->escape_contents) {
            $textline .= $this->csv_textquote
                         .addcslashes($field, "\x0..\x1F\x7F..\x9F\\".$this->csv_textquote)
                         .$this->csv_textquote;
          }
          else {
            $textline .= $this->csv_textquote.$field.$this->csv_textquote;
          }
        }
      }

      // TODO: Don't write here, but let Logger class do that:
      if (fwrite($fhandle, $textline."\n") === FALSE) {
        throw new Exception('Cannot write to file: '.$filename);
      }
      fclose($fhandle);
    }
    else {
      throw new Exception('File not writable: '.$filename);
    }
  }
}
