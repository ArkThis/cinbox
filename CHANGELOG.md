# Changelog

(For style- and content-guidelines see [Common Changelog](https://common-changelog.org/))


## v2.1.0 - 2025-01-24

### Fixed

  - Fixed [Issue #15: Empty item-id folder left after successful move to archive](https://github.com/ArkThis/cinbox/issues/15):
  - Fixed [Issue #16: Staging folder won't work properly if lowest target folder ain't ITEM_ID](https://github.com/ArkThis/cinbox/issues/16)
  - Fixed [Issue #17: Task name includes class-hierarchy](https://github.com/ArkThis/cinbox/issues/17)
  - Fixed [Issue #18: Crash/exit when loading "CopyToTarget" task.](https://github.com/ArkThis/cinbox/issues/18)
  - Fixed [Issue #20: Error: "Too many open files" when having ~1070 entries in "done" folder](https://github.com/ArkThis/cinbox/issues/20)
  - Fixed [Issue #22: Exception 'not found' when INI config file not found](https://github.com/ArkThis/cinbox/issues/22)

  - Fixed issue with false-positive hashcode-mismatch if files changed between item runs.
    See: [Issue #24: Hashcode mismatch due to outdated hashcode from previous run](https://github.com/ArkThis/cinbox/issues/24)
  - Fixed [Issue #26: Placeholder for time "SECOND" is "MINUTE"](https://github.com/ArkThis/cinbox/issues/24)
  - Fixed [Issue #27: Token files: JSON encoded paths have slashes escaped](https://github.com/ArkThis/cinbox/issues/27)
  - Fixed date/time placeholder variables: They update per-Item now.
    See: [Issue #31: Values for date/time placeholders in config are frozen at start of CInbox](https://github.com/ArkThis/cinbox/issues/31)
  - Fixed [Issue #35: If Item state cannot be moved from todo to in_progress folder, processing still starts](https://github.com/ArkThis/cinbox/issues/35)

  - Minor code cleanups here and there.


### Added

  - Added Documentation for `WORK_TIMES` feature (introduced in v2.0.0)

  - Improved code comments and style.

  - Added simple script for generating Doxygen HTML code documenation.

  - Added support for hidden files in `FILES_VALID` option.

  - Added done/error state may create a "token" file (JSON format).
    This can be used to trigger other processes, depending on an Item's CInbox processing status.
    Token files contain unix-time and a human-readable timestamp.

  - Added inter-task communication option (aka `Item memory`).
    This allows future features where tasks can access information from other
    tasks consistently throughout the processing of an Item.

  - Added support laaaaarge number of items in one folder (>1024).

  - Added logging of commandline arguments passed to plugin-scripts.
    See [Issue #38: Lack of parameter-logging for PreProcs/PostProcs task](https://github.com/ArkThis/cinbox/issues/38)



## v2.0.0 - 2023-10-11

> **NOTE:**
> **This version has *MAJOR CHANGES* that will break backwards compatibility,
> due to file-position-changes and different foldernames.**
>
> These changes were necessary to properly support the PHP "autoloading
> standard" ([PHP PSR-4](https://www.php-fig.org/psr/psr-4/) and PHP Composer
> compatibility.
>
> Sorry for the inconvenience.


### Changed

  - Changed folder/file-layout to conform with [PHP Composer](https://getcomposer.org/).

  - Renamed "bin" to "src":
    You will need to update the paths of (mainly Pre/PostProc scripts) in existing cinbox.ini files.

  - Switched to [PHP namespaces](https://www.php.net/manual/en/language.namespaces.php) support.
    (In order to be better supportable with more recent PHP versions, and compatible with Composer-packaged libs).


### Added

  - Feature to set 'working hours' when it's okay to process new items.
    (New config option `WORK_TIMES[]`)

  - Dependency on [Cron expression parser lib](https://github.com/dragonmantank/cron-expression/).

  - Composer-stuff and `vendor` subfolder.
    Yes, I know the 'vendor` folder should not be added to version control, but
    I want a code checkout to be self-sufficient (just in case).
    I'm new to Composer.



## v1.4.0 - 2023-10-11

### Changed

  - Allow setting of "staging" area folder when copying target.
    (This replaces the previous "temp_" folder renaming prefix mechanism.
    `TARGET_STAGE` option is now mandatory for tasks using `TARGET_FOLDER`.)



## v1.3.1 - 2023-09-22

### Added

  - Config option to set temporary folder
    (instead of hardcoded to PHP's `sys_get_temp_dir()`)

