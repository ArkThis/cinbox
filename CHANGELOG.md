# Changelog

(For style- and content-guidelines see [Common Changelog](https://common-changelog.org/))


## v1.4.0 - 2023-10-11

### Changed

  - Allow setting of "staging" area folder when copying target.
    (This replaces the previous "temp_" folder renaming prefix mechanism.
    `TARGET_STAGE` option is now mandatory for tasks using `TARGET_FOLDER`.)


## v1.3.1 - 2023-09-22

### Added

  - Config option to set temporary folder
    (instead of hardcoded to PHP's `sys_get_temp_dir()`)

