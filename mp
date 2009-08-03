#!/usr/bin/php
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

require_once('Migrator.php');

// get options
$longopts = array();
$shortopts  = "";

$shortopts .= "d:"; // -d --dir <MigrationsDir>
$longopts[] = "dir";

$shortopts .= "t:"; // -t --target <targetVersion> => Version to migrate to
$longopts[] = "target:";

$shortopts .= "c:"; // -c --command <command> => migrate, create, clean
$longopts[] = "command:";

$shortopts .= "r"; // -r --clean => run clean() first
$longopts[] = "clean";

$shortopts .= "x:"; // -r --dsn <PDO DSN> => A PDO DSN
$longopts[] = "dsn:";

$shortopts .= "f"; // -f --version-in-file => Use migrations/version.txt to store the version
$longopts[] = "version-in-file";

$shortopts .= "V:"; // -V --set-version <version> => A version #.
$longopts[] = "set-version:";

$shortopts .= "v";  // -v --verbose => verbose
$longopts[] = "verbose";

$shortopts .= "q";  // -q --quiet => supress all logging
$longopts[] = "quiet";

$shortopts .= "h";  // -h --help => print help
$longopts[] = "help";

$cliOpts = @getopt($shortopts, $longopts);

if (isset($cliOpts['h']))
{
    print <<<END
migrate usage:
  -c --command [migrate|create|clean]
     migrate options:
        -t --target <TargetVersion> or 0|up|down|head|redo
           * redo is an alias for down + up
        -r --clean Run clean first
  -x --dsn <PDO DSN>
    NOTE: if you supply a DSN then mp will automatically store the version number
    in your database in a table named public.mp_version.
    If you do not supply a dsn then you will need to use the -f flag to tell mp
    to use the filesystem for storage of the version.
  -f --version-in-file Store the version on the filesystem.
  -v --verbose Verbose messages
  -q --quiet Supress all message
  -h --help Show this help
  -d --dir <MigrationDir>
  -V --set-version <version> Set the current version number maintained by mp to be <version>.
                             Does not run migrations; used to initialize mp for an existing system.

NOTE: long options are not yet implemented.

Default command is "migrate to latest".

END;
    exit(0);
}

// default settings
if (!isset($cliOpts['c']))
{
    $cliOpts['c'] = 'migrate';
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
try {
    $m = new Migrator($opts);

    if (isset($cliOpts['V']))
    {
        try {
            $m->setVersion($cliOpts['V']);
            exit(0);
        } catch (Exception $e) {
            print "Error setting version: [" . get_class($e) . "] " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    if (isset($cliOpts['r']))
    {
        try {
            $m->clean();
        } catch (Exception $e) {
            print "Error running clean: [" . get_class($e) . "] " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    // are we migrating, cleaning, or creating?
    switch ($cliOpts['c']) {
        case 'create':
            $m->createMigration();
            exit(0);
            break;
        case 'clean':
            $m->clean();
            exit(0);
            break;
        case 'migrate':
            $targetVersion = NULL;
            if (isset($cliOpts['t']))
            {
                $targetVersion = $cliOpts['t'];
            }

            if ($targetVersion === NULL)
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
            break;
    }
} catch (Exception $e) {
    print "Error running migration: [" . get_class($e) . "] " . $e->getMessage() . "\n";
    exit(1);
}
