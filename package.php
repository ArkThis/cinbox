#!/usr/bin/env php
<?php

/**
 * This creates a PHAR archive of the Common-Inbox.
 * 
 * @author Peter Bubestinger-Steindl
 * @copyright
 *  Copyright 2017, Peter Bubestinger-Steindl
 *  (License: <a href="http://www.gnu.org/licenses/gpl.html">GNU General Public License (v3)</a>)
 *
 * @see
 *  - <a href="http://php.net/manual/en/book.phar.php">Official PHAR (=<b>PH</b>p <b>AR</b>chive): Documentation</a>
 */

$setting = 'phar.readonly';
if (ini_get($setting) == true)
{
    printf("\nCannot create PHAR: You must configure '%s = 0' in your php.ini first!\n", $setting);
    die(1);
}

try
{
    printf("Creating PHAR archive...\n");

    $target = 'cinbox.phar';
    $targetCompressed = $target . '.gz';
    $alias = basename($target);

    $p = new Phar($target, 0, $alias);

    if (file_exists($targetCompressed)) unlink($targetCompressed);
    $p2 = $p->compress(Phar::GZ);

    $p2->buildFromDirectory('.', '/src|locale/');
    $p2->setStub(
            $p->createDefaultStub('src/cinbox.php', 'src/cinbox.php')
            );
}
catch (Exception $e)
{
    printf("Error:\n%s\n", $e->getMessage());
}


?>
