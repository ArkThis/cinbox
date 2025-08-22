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
use \ArkThis\CInbox\CIExec;         // for running external commands
#use \ArkThis\Helper;
#use \Exception as Exception;


/**
 * @author Peter Bubestinger-Steindl (cinbox (at) ArkThis.com)
 * @copyright
 *  Copyright 2025 ArkThis AV-RD e.U.
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://www.ArkThis.com/products/cinbox/">CInbox product website</a>
 *  - <a href="http://www.ArkThis.com/">AV-RD website</a>
 *  - <a href="https://fsfe.org/about/basics/freesoftware.en.html">FSFE: What is Free Software?</a>
 *  - <a href="http://www.gnu.org/licenses/gpl.html">The GNU General Public License</a>
 */
// TODO: Change the class name to match your case:
class TaskMediaConch extends TaskFFmpeg
{
    /* ========================================
     * CONSTANTS
     * ======================================= */

    const TASK_LABEL = 'Run MediaConch profile validator'; ///< Human readable task name/label.

    /**
     * @name Task Settings
     * Names of config settings used by this task
     */
    //@{
    // The following arrays must have identical entries, that are related to each other.
    // `match + policy = target (in target-format) => triggering reaction based on result`
    const CONF_SOURCES = "MCONCH_IN";         ///< Glob patterns to match (media source files)
    const CONF_TARGETS = "MCONCH_OUT";        ///< Target output filenames (per match)
    const CONF_RECIPES = "MCONCH_RECIPES";    ///< Commandline to call

    const CONF_REACTIONS = "MCONCH_REACTIONS";  ///< What do to on which policy result?
    //@}


    /* ========================================
     * PROPERTIES
     * ======================================= */

    // Class properties are defined here.
    // Basic ones like $recipes, $sources and $targets are inherited.
    private $reactions;                                 ///< @see #CONF_REACTIONS

    private $targetFormatsAllowed;                      ///< List of MediaConch output format options.
    private $reactionsAllowed;                          ///< List of what happens if...?


    /* ========================================
     * METHODS
     * ======================================= */

    function __construct(&$CIFolder)
    {
        parent::__construct($CIFolder, self::TASK_LABEL);

        // This is used for calling external tool later:
        $this->exec = new CIExec();     // class property provided by TaskExec.

        // See manpage for details on output options:
        // https://manpages.debian.org/unstable/mediaconch/mediaconch.1.en.html
        $this->targetFormatsAllowed = array(
            'text',     // -ft
            'xml',      // -fx
            'maxml',    // -fa
            'html',     // -fh
            'failpass'  // -fs
        );

        // TODO: This is not completely obvious yet which return states exist here...
        $this->reactionsAllowed = array(
            'warning',      // on fail, throw warning - but continue (aka "pbc"?)
            'abort',        // on fail, throw error and abort
        );
    }


    /**
     * @name Common task functions
     */
    //@{

    /**
     * Load settings from config that are relevant for this task.
     *
     * @retval boolean
     *  True if everything went fine, False if an error occurred.
     */
    protected function loadSettings()
    {
        // Disabled, because FFmpeg Task is too specific in settings.
        // Collides with deriving methods/properties from it.
        // Still makes sense to re-use ffmpeg helper stuff like source/target resolution, etc.
        #if (!parent::loadSettings()) return false;

        $l = $this->logger;
        $config = $this->config;

        $l->logLineH1(32);

        // -------
        // Load media source list:
        // TODO: do some checking on the patterns?
        $this->sources = $config->get(self::CONF_SOURCES);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->sources)) return $this->skipIt("sources empty");
        // TODO: throw InvalidArgumentException?
        if (!$this->optionIsArray($this->sources, self::CONF_SOURCES)) return false;
        $l->logDebug(sprintf(
                    _("Patterns for MediaConch sources (input): %s"),
                    implode(', ', $this->sources)
                    ));

        printf("one\n");//DELME

        // -------
        // Load target list:
        $this->targets = $config->get(self::CONF_TARGETS);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->targets)) return $this->skipIt("targets empty");
        // TODO: throw InvalidArgumentException?
        if (!$this->optionIsArray($this->targets, self::CONF_TARGETS)) return false;
        $l->logDebug(sprintf(
                    _("List of MediaConch targets (output): %s"),
                    implode(', ', $this->targets)
                    ));
        printf("two\n");//DELME

        // -------
        // Load recipe list:
        $this->recipes = $config->get(self::CONF_RECIPES);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->recipes)) return $this->skipIt("recipes empty");
        // TODO: throw InvalidArgumentException?
        if (!$this->optionIsArray($this->recipes, self::CONF_RECIPES)) return false;
        $l->logDebug(sprintf(
                    _("List of MediaConch recipes: %s"),
                    implode(', ', $this->recipes)
                    ));
        printf("three\n");//DELME

        // -------
        // Load reaction list:
        // TODO: do some checking on the patterns?
        $this->reactions = $config->get(self::CONF_REACTIONS);
        // Task is optional, therefore it is skipped if one setting is empty:
        if(empty($this->reactions)) return $this->skipIt("reactions empty");
        // TODO: throw InvalidArgumentException?
        if (!$this->optionIsArray($this->reactions, self::CONF_REACTIONS)) return false;
        $l->logDebug(sprintf(
                    _("List of MediaConch reactions: %s"),
                    implode(', ', $this->reactions)
                    ));
        printf("four\n");//DELME

        $l->logHeader2("blip!\n\n"); //DELME

        // Must return true on success:
        return true;
    }


    /**
     * Prepare everything so it's ready for processing.
     * For commandline recipes it resolves input glob patterns and creates
     * matching output filenames for each source/target pair.
     *
     * Afterwards, $this->todoList should be properly populated with
     * commandline calls ready to run.
     *
     * @retval boolean
     *  True if task shall proceed. False if not.
     */
    public function init()
    {
        // DISABLED: ffmpeg parent's code too task-specific.
        #if (!parent::init()) return false;

        // TODO: The check if required binaries (extract from recipe) exists and is
        //       valid is done before executing each command/recipe in run().

        // Check if config-tuples are complete:
        try
        {
            $this->checkArrayKeysMatch2(array(
                        self::TODO_IN => $this->sources,
                        self::TODO_OUT => $this->targets,
                        self::TODO_RECIPES => $this->recipes,
                        ));
        }
        catch (Exception $e)
        {
            $l->logException(sprintf(_("Not all FFmpeg config options are configured. Please check config file!")), $e);
            $this->setStatusConfigError();
            return false;
        }

        // Map config setting placeholder-using config strings:
        $this->populateTodoList(
            array(
                self::TODO_IN => $this->sources,
                self::TODO_OUT => $this->targets,
                self::TODO_RECIPES => $this->recipes,
            )
        );

        // Must return true on success:
        return true;
    }


    /**
     * Perform the actual steps of this task.
     *
     * @retval boolean
     *  True if task shall proceed. False if not.
     */
    public function run()
    {
        if (!parent::run()) return false;
        // TODO: Add here what the task is supposed to do.

        // Must return true on success:
        $this->setStatusDone();
        return true;
    }


    /**
     * Actions to be performed *after* run() finished successfully;
     *
     * @retval boolean
     *  True if everything went fine, False if an error occurred.
     */
    public function finalize()
    {
        if (!parent::finalize()) return false;

        // TODO: Optional. Do things here that need to be done *after*
        //       the actual task has finished.
        //       For example clean-up things or so.

        // Must return true on success:
        return true;
    }

    //@}


    /**
     * @name Task-specific methods
     */
    //@{

    // TODO: Add your methods here.
    // Default type is 'protected'. Use 'public' functions only where necessary.

    //@}

}

?>
