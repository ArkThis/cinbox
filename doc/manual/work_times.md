# WORK\_TIMES[] (Array)

This configuration option is used to declare at what times/days CInbox is "allowed" to process new Items.
For example, this option allows you to limit the CInbox to only do its work outside of peak business hours, to balance the load on CPU or network resources.

You can add more than 1 WORK\_TIMES option, in order to declare more complex "working hours" patterns.
The syntax is compatible to the well known `[cron/crontab](https://en.wikipedia.org/wiki/Cron)`.


## Here's how it works:

```
#              
#              ┌───────────── minute (0 - 59)
#              │ ┌───────────── hour (0 - 23)
#              │ │ ┌───────────── day of the month (1 - 31)
#              │ │ │ ┌───────────── month (1 - 12)
#              │ │ │ │ ┌───────────── day of the week (0 - 6) (Sunday to Saturday;
#              │ │ │ │ │                                   7 is also Sunday on some systems)
#              │ │ │ │ │
#              │ │ │ │ │
WORK_TIMES[] = * * * * *
```

You must add "[]" to "WORK\_TIMES", because it can appear multiple values (=appear multiple times in the INI).


## Some examples

  * Monday to Friday, only 9-17h:  
    `WORK_TIMES[] = * * * * 1-5

  * Only on weekends (Sat/Sun):
    `WORK_TIMES[] = * * * * 0,6,7`

  * Every 5 minutes:
    `WORK_TIMES[] = */5 * * * *`

You can use any cron-syntax generator of your choice. Here are some for example:

  * https://crontab-generator.org/
  * https://crontab.cronhub.io/
  * https://crontab.guru/
  * https://freeformatter.com/cron-expression-generator-quartz.html
  * http://www.crontabgenerator.com/


## Behavior 

In the main loop, iterating through each item in the TODO folder, CInbox will check the WORK_TIMES entries if any of them is currently "due". If any cron pattern matches, the next item will be processed normally.

  * The WORK\_TIMES option has *no effect* on any ongoing execution of an Item.
  * This means that even if an Item has already started processing its Tasklist, it will finish normally.
  * The WORK\_TIMES only define when a *next* Item may be started or not.

You can, for example, define 2 patterns to define the following scenario:
Start new Items only at:

  * `* 12-13 * * 1`     (Monday, between 12-13h)
  * `*/15 * * * 5`      (Friday, the whole day, every 15 minutes)

Just add both lines as 2 WORK_TIMES[] config lines in your cinbox.ini:

```
WORK_TIMES[] = * 12-13 * * 1
WORK_TIMES[] = * `*/15 * * * 5
```

When the current timestamp is "outside of working hours", a simple dot "." will be printed to show that the CInbox isstill alive. Above those dots should be a line showing the next processing date-time:

> "Next run date is: 2023-01-03 20:15 (*/5 * * * *)"

This line may appear multiple times, as for each WORK\_TIMES[] option set in the config file, its next run date will be shown. Each pattern will print a maximum of 1 line, even if the timestamp is identical.


## Inner workings

  * The current default (constant in class `CInbox`) sleep-timeout between each
    check for a new Item is 45 seconds.

  * This value can be set arbitrarily, but must be less-than 60 seconds.
    Otherwise, there may be crontab patterns that "escape" the test-window of
    the loop, because of the cron-precision being full minutes.

  * The only effect the delay in this loop can have is:
    More "text noise" on log/screen output, due to more "not due yet" messages.
