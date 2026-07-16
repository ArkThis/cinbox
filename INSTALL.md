# Installing Common Inbox (CInbox)

Version: 2026-07-16

## Supported Operating Systems

On the following distributions, CInbox has been developed and tested:

  * Debian 10,12,13
  * RHEL


## Required system packages

The following packages are required for CInbox to run:

  * php-cli:
    PHP CommandLineInterface.
    NOTE: The package "php" usually installs the web-server module, NOT the commandline tool.

  * php-mbstring, php-intl:
    These are required for handling different character encodings and languages.

  * coreutils:
    Should already be installed, but added here just to be sure.

  * git:
    Cinbox releases are currently handled by checking out release-tag versions of the source.
    It is also used to properly handle updates.
    
