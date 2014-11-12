#!/usr/bin/env php
<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 syntax=php: */
/**
 * Copyright (c) 2009 Alan Pinstein <apinstein@mac.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

if (strpos('@php_bin@', '@php_bin') === 0) {  // not a pear install
  $prefix = dirname(__FILE__);
} else {
  $prefix = 'mp';
}
require_once "{$prefix}/Migrator.php";


// get options
$longopts = array();
$shortopts  = "";

$shortopts .= "x:"; // -r --dsn <PDO DSN> => A PDO DSN
$longopts[] = "dsn:";

$shortopts .= "f"; // -f --version-in-file => Use migrations/version.txt to store the version
$longopts[] = "version-in-file";

$shortopts .= "d:"; // -d --dir <MigrationsDir>
$longopts[] = "dir";

$shortopts .= "m::"; // -m --migrate <targetVersion> => Version to migrate to
$longopts[] = "migrate::";

$shortopts .= "r"; // -r --clean => run clean() first
$longopts[] = "clean";

$shortopts .= "l"; // -l --list => list all migrations
$longopts[] = "list";

$shortopts .= "n"; // -n --create => create a new migration
$longopts[] = "create";

$shortopts .= "s"; // -s --show-version => show the current version
$longopts[] = "show-version";

$shortopts .= "V:"; // -V --set-version <version> => A version #.
$longopts[] = "set-version:";

$shortopts .= "v";  // -v --verbose => verbose
$longopts[] = "verbose";

$shortopts .= "q";  // -q --quiet => supress all logging
$longopts[] = "quiet";

$shortopts .= "h";  // -h --help => print help
$longopts[] = "help";

if (version_compare(phpversion(), "5.3.0", '>='))
{
    $cliOpts = getopt($shortopts, $longopts);
}
else
{
    print "NOTE: long options not supported on PHP versions before 5.3.\n";
    $cliOpts = getopt($shortopts);
}

if (isset($cliOpts['h']))
{
    print <<<END
migrate usage:
  VERSION STORAGE (must use one or the other)
  -x --dsn <PDO DSN>
    NOTE: if you supply a DSN then mp will automatically store the version number
    in your database in a table named public.mp_version.
    If you do not supply a dsn then you will need to use the -f flag to tell mp
    to use the filesystem for storage of the version.
  -f --version-in-file Store the version on the filesystem.

  COMMANDS
  -r --clean Run clean before doing anything else.
  -n --create Create a new migration.
  -s --show-version Shows the current version.
  -V --set-version <version> Set the current version number maintained by mp to be <version>.
                             Does not run migrations; used to initialize mp for an existing system.
  -m --migrate <TargetVersion> or 0|up|down|head|redo
     * redo is an alias for down + up
  -l --list List all migrations in VERSION - DESCRIPTION format

  OPTIONS
  -v --verbose Verbose messages
  -q --quiet Supress all message
  -h --help Show this help
  -d --dir <MigrationDir>

NOTE: long options are not yet implemented.
NOTE: on PHP 5.3 options should be passed as -mX or -m=X, not -m X, due to a bug in getopt.

Default command is "show status".

END;
    exit(0);
}

// create migrator
$opts = array();
if (isset($cliOpts['v']))
{
    $opts[Migrator::OPT_VERBOSE] = true;
}
if (isset($cliOpts['q']))
{
    $opts[Migrator::OPT_QUIET] = true;
}
if (isset($cliOpts['d']))
{
    $opts[Migrator::OPT_MIGRATIONS_DIR] = $cliOpts['d'];
}
if (isset($cliOpts['x']))
{
    // use DB
    $opts[Migrator::OPT_PDO_DSN] = $cliOpts['x'];
    $opts[Migrator::OPT_VERSION_PROVIDER] = new MigratorVersionProviderDB();
}
else
{
    // don't use DB
    if (!isset($cliOpts['f']))
    {
        print "If you aren't supplying a DSN, you must use the -f/--version-in-file flag to force using a file for storag of versioning info.\nThis is done to prevent accidentially storing the version number in two places which could have disastrous consequences.\n";
        exit(1);
    }
}
// default command
if (!(isset($cliOpts['r']) or isset($cliOpts['n']) or isset($cliOpts['s']) or isset($cliOpts['V']) or isset($cliOpts['m']) or isset($cliOpts['l'])))
{
    $cliOpts['s'] = true;
}
try {
    $opts[Migrator::OPT_OFFER_MANIFEST_UPGRADE] = true;
    $m = new Migrator($opts);

    if (isset($cliOpts['r']))
    {
        $m->clean();
    }

    if (isset($cliOpts['n']))
    {
        $m->createMigration();
        exit(0);
    }

    if (isset($cliOpts['s']))
    {
        print "Current version: " . $m->getVersion() . "\n";
    }

    if (isset($cliOpts['l']))
    {
        $m->listMigrations();
    }

    if (isset($cliOpts['V']))
    {
        try {
            $m->setVersion($cliOpts['V']);
            print "Current version is now: " . $m->getVersion() . "\n";
            exit(0);
        } catch (Exception $e) {
            print "Error setting version: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    if (isset($cliOpts['m']))
    {
        $targetVersion = $cliOpts['m'];
        if ($targetVersion === false)
        {
            $targetVersion = Migrator::VERSION_HEAD;
        }
        if ($targetVersion === 'redo')
        {
            $ok = $m->migrateToVersion(Migrator::VERSION_DOWN);
            if ($ok)
            {
                $ok = $m->migrateToVersion(Migrator::VERSION_UP);
            }
            exit($ok === true ? 0 : 1);
        }
        else
        {
            $ok = $m->migrateToVersion($targetVersion);
            exit($ok === true ? 0 : 1);
        }
    }
} catch (Exception $e) {
    print "Error running migration: " . $e->getMessage() . "\n";
    exit(1);
}
