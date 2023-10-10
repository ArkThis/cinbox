# Changelog

(For style- and content-guidelines see [Common Changelog](https://common-changelog.org/))


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

