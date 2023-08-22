# Currently Known issues

(2022-03-20)


## 1) BUG: if logfile exists in "done" folder: "could not move" error

This happens when a previously processed item is reset, and therefore still has its logfile in the "done" folder.
The item is moved though, but this error/warning is wrong.
Please check + fix.


## 2) BUG: Logstyle=cv causes all output to be silent

When setting the logstyle to "CV", something goes wrong and there is no more output shown to the console.
I remember that there was an issue with the CV library if the hardcoded (!) path "/mnt/dlp-storage" was not found. Maybe this is related?
Yes: If "/mnt/dlp-storage" doesn't exist, it won't log anything! That folder-check is the unnecessary requirement that should be recoded to be irrelevant. Maybe throw a verbose warning if the path doesn't exist.


## 3) Plugins: Allow relative path

It seems that currently the path to plugins in the cinbox.ini requires an absolute path.
It would be nicer/better to allow relative paths.


## 4) Task "DirListCSV" seems to run successfully even if no listing file was written.

This may happen if the "DIRLIST_FILE" parameter ([__INBOX__] section) is not set.
TODO: Check why this is happening, as a sane default value should be assumed and
used for DIRLIST_FILE anyways.


## 5) Temporary folder is being cleaned (garbage collected) by the OS.

The policy for the good old "/tmp" folder has changed in recent years so that it
is automatically cleaned out the by OS. This breaks CInbox workflows as its data
required for state-keeping between runs must not be touched.
Suggestion: Add an option to the INI file allowing the temp folder to be
arbitrarily set by the config.
new default may be: "/var/cinbox/$NAME"


## 6) Sometimes, subfolders of the item target location may still contain "temp\_"
prefix in their names, even if their paren (=item) folder was finished copying properly.
This must not happen.


## 7) If INI configuration is changed during runtime, CInbox must be restarted.

Warning is displayed that config is out of date, but it is not reloaded automatically.



# Other remarks

  * **Order of RenameTarget and HashValidate tasks was swapped in v1.0-RC8**
    That's *important* for users upgrading from versions prior to v1.0-RC8:
    If you have your own TASKLIST[] configured, make sure that the tasks now
    appear in the following order:
      - HashValidate
      - RenameTarget



# Wishlist

  * Check free diskspace on target

