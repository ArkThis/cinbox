<?php
/* LICENSE. */

class ArchiveItem {
  // Media archive item PHP class (dummy to provide what is needed by FileCV)
  // This class implements various methods for dealing with media archive items.
  // Could be merged with or replaced by CIItem or CInbox classes.
  //

  // Constants for assembling paths
  // Default archive root directory.
  // TODO: This should not be hardcoded, but configurable somewhere.
  const DIR_ARCHIVE_ROOT_DEFAULT = '/mnt/dlp-storage';

  // Supported media types. A 'directory' type will ignore
  // directory structures and directly use the given archive root as the
  // directory for all data.
  const SUPPORTED_MEDIA_TYPES = array('directory');

  private $dir_archive_root;
  private $item_id;
  public $item_type = 'directory';

  function __construct($item_type, $item_id, $dir_archive_root = null) {
    // Set item type.
    if (in_array($item_type, self::SUPPORTED_MEDIA_TYPES)) {
      $this->item_type = $item_type;
    }
    else {
      throw new Exception(sprintf(
          'Unknown media type: %s (allowed types: %s)',
          $item_type,
          implode(' ', self::SUPPORTED_MEDIA_TYPES)));
    }
    // Set item ID/signature.
    if (strlen($item_id) &&
        preg_match('/^\/?\w/', $item_id)) {
      $this->item_id = $item_id;
    }
    elseif (strlen($item_id)) {
      throw new Exception(sprintf(
          'The item ID is not valid: %s',
          $item_id));
    }
    else {
      throw new Exception('An item ID is required to create an item.');
    }
    // Set archive root directory.
    if (!is_null($dir_archive_root) && strlen($dir_archive_root)) {
      $this->dir_archive_root = $dir_archive_root;
    }
    else {
      $this->dir_archive_root = self::DIR_ARCHIVE_ROOT_DEFAULT;
    }
    if (!is_dir($this->dir_archive_root)) {
      throw new Exception(sprintf(
          'The archive root does not exist: %s',
          $this->dir_archive_root));
    }
  }

  private function _get_signature_paths() {
    // Splits thre archive item's signature and returns the pieces of the
    // final bucketized path.

    //if ($this->item_type == 'directory')
    return array(dirname($this->item_id),
                 basename($this->item_id));
  }

  public function get_id() {
    return $this->item_id;
  }

  public function get_signature() {
    return $this->get_id();
  }

  public function get_dir($spec) {
    // Return a directory corresponding to a given specification.
    list($item_prefixpath, $item_sigpath) = $this->_get_signature_paths();
    switch ($spec) {
      // archive root
      case 'DIR_ARCHIVE_ROOT':
        return $this->dir_archive_root;
        break;
      case 'metadata':
        return $item_prefixpath.self::addpath($item_sigpath);
        break;
    }
    // Throw an error when an invalid spec is handed over.
    throw new Exception('Unknown directory spec: '.$spec);
    return null;
  }

  public static function addpath($pathpart) {
    return (strlen($pathpart)?DIRECTORY_SEPARATOR:'').$pathpart;
  }
}
