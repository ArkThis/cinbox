# CInbox: A Common Inbox engine for archiving data


CInbox v1.x
================================

Known issues
--------------------------------

  * If INI configuration is changed during runtime, CInbox must be restarted.
    (Warning is displayed that config is out of date, but it is not reloaded automatically)

  * IMPORTANT for users upgrading from versions prior to v1.0-RC8:
    Order of RenameTarget and HashValidate tasks was swapped
    due to changes from v1.0-RC7 to v1.0-RC8.
    If you have your own TASKLIST[] configured, make sure that the tasks now
    appear in the following order:
      - HashValidate
      - RenameTarget


Wishlist
--------------------------------

  * Check free diskspace on target
