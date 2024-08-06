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

namespace ArkThis\CInbox;

use \ArkThis\Logger;
use \Exception as Exception;
require_once('placeholders.inc.php');


/**
 * This class manages the Common Inbox configuration.
 * Functions include: loading from file, resolving placeholders, etc.
 *
 * In the future it can be extended to also write config files.
 *
 * The correct way to load and initialize the config is:
 *   - Provide config filename (constructor or 'setConfigFile')
 *   - Run 'initPlaceholders()' at which point you want the date/time placeholders to be resolved.
 *   - Load config from file: loadConfigFromFile()
 *   - Access config as INI-array: getConfigArray()
 *   - Load config settings from INI section: loadSettings()
 *   - Use config settings: get()
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
class CIConfig
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    const CONF_SECTION_DEFAULT = '__DEFAULT__';
    const CONF_SECTION_UNDEFINED = '__UNDEFINED__';



    /* ========================================
     * PROPERTIES
     * ======================================= */

    private $logger;                                    // Logging handler

    private $configFile = null;                         // Filename of config file.
    private $configFileProps = null;                    // Properties of config file (stats() array)
    private $configChanged = null;                      // True if config has changed during runtime and should be reloaded.
    private $configRaw;                                 // Config as string. Raw from file.
    private $configResolved;                            // Config as string with placeholders resolved.
    private $configArray;                               // Configuration as returned from 'parse_ini_string';

    private $settings;                                  // Array that stores individual settings for easy access
    private $placeholders;                              // Array with common placeholder values - resolved!



    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$logger, $configFile=null)
    {
        $this->logger = $logger;

        if (!empty($configFile))
        {
            $this->setConfigFile($configFile);
        }
    }


    /**
     * Initializes values for common placeholders, such as date/time values,
     * PHP_SELF, etc.
     * This allows consistent values across certain execution ranges.
     */
    public function initPlaceholders($arguments=null)
    {
        $this->placeholders = array();                  // NOTE: This clears existing placeholder values

        $datetime = array(
                __DATETIME__ => date('Ymd_His'),
                __YEAR__ => date('Y'),
                __MONTH__ => date('m'),
                __DAY__ => date('d'),
                __HOUR__ => date('H'),
                __MINUTE__ => date('m'),
                __SECOND__ => date('s'),

                // Information about the currently running script:
                __PHP_SELF__ => $_SERVER['PHP_SELF'],
                __PHP_SELF_DIR__ => dirname($_SERVER['PHP_SELF']),
                __PHP_SELF_NAME__ => basename($_SERVER['PHP_SELF'])
                );

        if (is_array($arguments))
        {
            // Add values for additional placeholders here already:
            $this->placeholders = array_merge($this->placeholders, $arguments, $datetime);
        }
        else
        {
            // Only merge existing placeholder values:
            $this->placeholders = array_merge($this->placeholders, $datetime);
        }
    }


    /**
     * Adds a new placeholder=value pair to existing placeholder list.
     * Used to add values to be present during resolving of placeholders.
     *
     * Must be called *after* 'initPlaceholders()', because otherwise additions
     * to placeholders will be lost.
     */
    public function addPlaceholder($name, $value)
    {
        $l = $this->logger;

        if (empty($name))
        {
            throw new Exception(_("addPlaceholder: No name given."));
        }
        // No check for empty($VAlue), because values are allowed to be empty.

        $l->logDebug(sprintf(_("Adding value for '%s = %s'."), $name, $value));

        $arguments = array($name => $value);
        $placeholders = array_merge($this->placeholders, $arguments);
        $this->placeholders = $placeholders;

        return true;
    }

    /**
     * Returns the current placeholders array as-is.
     */
    public function getPlaceholders()
    {
        return $this->placeholders;
    }


    /**
     * Short for "setSetting".
     */
    public function set($name, $value)
    {
        $this->settings[$name] = $value;
    }


    /**
     * Short for "getSetting".
     */
    public function get($name)
    {
        if (isset($this->settings[$name]))
        {
            $setting = $this->settings[$name];
            if (is_string($setting)) $setting = trim($setting);
            return $setting;
        }
        else
        {
            return null;
        }
    }


    /**
     * Returns the currently loaded settings as array.
     * Read-only. Do not attempt to write to it.
     */
    public function getSettings()
    {
        return $this->settings;
    }


    /**
     * Returns the raw configuration string.
     * False if configuration has not yet been loaded.
     */
    public function getConfigRaw()
    {
        if (empty($this->configRaw))
        {
            return false;
        }

        return $this->configRaw;
    }


    /**
     * Returns the config block defined within INI section '$section'.
     * $arguments can optionally be provided to resolve placeholders.
     */
    public function getConfigForSection($section, $arguments=null)
    {
        $l = $this->logger;

        $l->logDebug(sprintf(_("Getting config for INI section '%s'..."), $section));

        if (empty($section))
        {
            throw new Exception(_("getConfigForSection: parameter \$section cannot be empty."));
        }

        // This must return an array. Checks should happen already during call of getConfigArray(), so none done here.
        $configArray = $this->getConfigArray($arguments);

        if (!isset($configArray[$section]))
        {
            // No config for section available. This is not an error.
            return false;
        }
        $configSection = $configArray[$section];

        return $configSection;
    }


    /**
     * Returns a configuration value retrieved from the resolved config array
     * by section + name.
     */
    public function getFromArray($section, $name, $arguments=null)
    {
        if (empty($name)) return false;

        $configSection = $this->getConfigForSection($section, $arguments);
        if (!is_array($configSection)) return false;
        if (!isset($configSection[$name])) return false;

        return $configSection[$name];
    }


    /**
     * Same as "getFromArray()", but reads from the DEFAULT section.
     */
    public function getFromDefault($name, $arguments=null)
    {
        return $this->getFromArray(self::CONF_SECTION_DEFAULT, $name, $arguments);
    }


    /**
     * Same as "getFromArray()", but reads from the UNDEFINED section.
     */
    public function getFromUndefined($name, $arguments=null)
    {
        return $this->getFromArray(self::CONF_SECTION_UNDEFINED, $name, $arguments);
    }


    /**
     * Returns the configuration section for DEFAULT.
     */
    public function getConfigForDefault($arguments=null)
    {
        $configSection = $this->getConfigForSection(self::CONF_SECTION_DEFAULT);
        return $configSection;
    }


    /**
     * Returns the configuration section for UNDEFINED.
     */
    public function getConfigForUndefined($arguments=null)
    {
        $configSection = $this->getConfigForSection(self::CONF_SECTION_UNDEFINED);
        return $configSection;
    }


    /**
     * Set the full path and filename of the configuration INI file to be used.
     *
     * Integrity checks are performed to make sure that $configFile is not empty,
     * that it is actually a file and has size greater than 0.
     *
     * No checks regarding the contents of the file are performed in this step, however.
     */
    public function setConfigFile($configFile=null)
    {
        $l = $this->logger;

        if (empty($configFile))
        {
            throw new Exception(_("Will not set configfile name to empty."));
        }

        if (!file_exists($configFile))
        {
            throw new Exception(sprintf(_("Config file not found: %s"), $configFile));
        }

        if (!is_file($configFile))
        {
            throw new Exception(sprintf(_("Invalid config file: %s"), $configFile));
        }

        $filesize = filesize($configFile);
        if ($filesize <= 0)
        {
            throw new Exception(sprintf(_("Config file is empty or has invalid size: %s (%d bytes)"), $configFile, $filesize));
        }

        $l->logInfo(sprintf(_("Config file is: %s"), $configFile));
        $this->configFile = $configFile;
    }


    /**
     * Returns the the currently set config file.
     *
     * @retval String
     *  Full path + name of the current config file.
     *  Null if config file is not set yet.
     */
    public function getConfigFile()
    {
        return $this->configFile;
    }


    /**
     * Returns the value of $configChanged.
     */
    public function hasChanged()
    {
        return $this->configChanged;
    }


    /**
     * Returns the file properties of the config file.
     */
    public function getConfigFileProps()
    {
        // Important to do "realpath()" here to resolve symlinks, etc:
        $configFile = realpath($this->configFile);

        if (!file_exists($configFile))
        {
            throw new Exception(_("Configfile does not exist: %s"));
        }

        return stat($configFile);
    }


    /**
     * Updates the file properties of the config file and stores
     * them in $configFileProps.
     *
     * This is used to monitor the config file during runtime in order to 
     * know if settings must be reloaded or not.
     *
     * @retval Array
     *  Returns the new contents of $configFileProps.
     */
    public function storeConfigFileProps()
    {
        $this->configFileProps = $this->getConfigFileProps();
        return $this->configFileProps;
    }


    /**
     * Compares the file properties stored in $configFileProps differ
     * from the ones currently loaded.
     * If difference is detected, $configChanged is also set to 'true'.
     *
     * @retval boolean
     *  'false' if no change was detected.
     *  'true'  if the config file has changed and should be reloaded.
     *  null    if $configFileProps are not set yet. #storeConfigFileProps() must be run before.
     */
    public function monitorConfigFileChanges()
    {
        $l = $this->logger;
        $oldProps = $this->configFileProps;

        // Nothing to compare to:
        if (empty($oldProps)) return null;

        if (!is_array($oldProps))
        {
            throw new Exception(sprintf(
                        _("configFileProps must be an array is of type '%s'."),
                        gettype($oldProps)));
        }

        $newProps = $this->storeConfigFileProps();

        // Comparing modification date + filesize:
        $sizeDiff = abs($oldProps["size"] - $newProps["size"]);
        $mtimeDiff = abs($oldProps["mtime"] - $newProps["mtime"]);

        // size/mtime same = no change.
        if (($sizeDiff + $mtimeDiff) == 0) return false;

        // Config file has changed.
        $this->configChanged = true;
        return true;
    }


    /**
     * Loads the configuration from a file an stores the contents in raw, unmodified form
     * as string in property '$configRaw'.
     * Resets $configChanged to 'false'.
     *
     * Triggers #storeConfigFileProps() to allow monitoring for runtime changes in config file.
     *
     * @see #storeConfigFileProps()
     */
    public function loadConfigFromFile()
    {
        $l = $this->logger;

        // If $this->configFile is set, we can assume sanity checks were made.
        $configFile = $this->configFile;

        $l->logMsg(sprintf(_("Loading config from file '%s'..."), realpath($configFile)));

        if (empty($configFile))
        {
            throw new Exception(_("Unable to load config from file: Config file not set."));
        }

        // Remember file properties to monitor changes and read the config from file:
        $this->storeConfigFileProps();
        $configRaw = file_get_contents($configFile);
        $this->configChanged = false;

        return $this->loadConfigFromString($configRaw);
    }


    /**
     * Equivalent to "loadConfigFromFile()", but without the step of reading it from the file.
     */
    public function loadConfigFromString($configString)
    {
        $l = $this->logger;

        if (empty($configString))
        {
            throw new Exception(_("Unable to load config from string: Empty string given."));
        }

        if (is_string($configString))
        {
            // At least we have something. Let's assume it's good:
            $this->configRaw = $configString;
            $l->logDebug(sprintf(_("configRaw:\n%s"), $this->configRaw));
            //$l->logInfo(_("Config loaded from string (raw)."));

            $this->configResolved = null;           // Reset this to avoid leftovers.

            return true;
        }

        return false;
    }


    /**
     * Parses the configuration in $configString in INI format.
     * Returns the configuration as array as returned from "parse_ini_string" function.
     * It also stores it in property '$configArray';
     *
     * No placeholders are resolved at this point.
     */
    public function parseConfigFromString($configString)
    {
        $l = $this->logger;

        if (empty($configString))
        {
            throw new Exception(_("Unable to parse config from string: Empty string given."));
        }

        // Here the magic of INI-to-array happens! :D
        $configArray = parse_ini_string($configString, $process_sections=true, INI_SCANNER_RAW);

        if (!is_array($configArray))
        {
            $l->logErrorPhp(_("Problem parsing INI contents"));
            $l->logDebug(_("Erroneous config string:"));
            $l->logDebug($l->getHeadline(Logger::HEADLINE_CHAR2) . "[Quote BEGIN]");
            $l->logDebug(print_r($configString, true));
            $l->logDebug($l->getHeadline(Logger::HEADLINE_CHAR2) . "[Quote END]");
            throw new Exception(_("Unable to parse config from string: Invalid config string (no array)."));
        }

        $this->configArray = $configArray;

        return $configArray;
    }

    /**
     * Resolves a given string using all placeholders known to this
     * config instance.
     */
    public function resolveString($string, $arguments=null)
    {
        // They must be initialized by now:
        if (empty($this->placeholders))
        {
            throw new Exception(_("Config placeholders not initialized."));
        }

        if ($arguments == null) { $arguments = array(); }

        $placeholders = array_merge($arguments, $this->placeholders);
        $resolved = \ArkThis\Helper::resolveString($string, $placeholders);

        return $resolved;
    }


    /**
     * This returns the configuration as INI string - *after* resolving placeholders.
     * It also updates the property: '$this->configResolved'.
     *
     * Use 'getConfigArray()' to get the INI configuration as associative array.
     */
    public function getConfigResolved($arguments=null)
    {
        $configResolved = $this->resolveString($this->configRaw, $arguments);
        $this->configResolved = $configResolved;

        return $configResolved;
    }


    /**
     * Sets this item's property 'configArray' to the contents of $configArray.
     * @param Array  $configArray     Configuration as array as returned by "parse_ini_file()".
     */
    public function setConfigArray($configArray)
    {
        if (!empty($configArray) && !is_array($configArray))
        {
            throw new Exception(_("Config is not an array."));
        }

        $this->configArray = $configArray;
    }


    /*
     *
     * This is the function to be used when the actual settings are
     * required that are to be used for processing.
     */
    public function getConfigArray($arguments=null)
    {
        // This is the right place to resolve placeholders.
        // Therefore, direct access to $this->configArray must be forbidden!

        $configArray = $this->parseConfigFromString($this->getConfigResolved($arguments));
        $this->setConfigArray($configArray);

        return $configArray;
    }


    /**
     * Loads the configuration items from an INI-array into the '$settings' property.
     * The INI-array *must not* contain multiple sections anymore, but only the content of *one* section.
     *
     * Default values must be loaded using 'setSettingsDefaults()' before calling 'loadSettings()' in order
     * to have default values instead of empty values where no setting was configured in config file.
     */
    public function loadSettings($config)
    {
        if (empty($config))
        {
            throw new Exception(_("Unable to load config: Empty config given."));
        }

        if (!is_array($config))
        {
            throw new Exception(_("Unable to load config: Not an array."));
        }

        foreach ($config as $name=>$value)
        {
            $this->set($name, $value);
        }

        return true;
    }


    /**
     * Initialize settings to default values.
     * Call this *before* loadSettings to provide non-empty values where defaults exists.
     *
     * WARNING: This overwrites already existing name/value settings.
     */
    public function setSettingsDefaults($defaultValues)
    {
        foreach ($defaultValues as $name=>$value)
        {
            $this->set($name, $value);
        }
    }




}

