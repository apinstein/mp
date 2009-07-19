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
        -t --target <TargetVersion> or 0|up|down
        -r --clean Run clean first
        -x --dsn <PDO DSN>
  -v --verbose Verbose messages
  -h --help Show this help
  -d --dir <MigrationDir>

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
    $opts[Migrator::OPT_PDO_DSN] = $cliOpts['x'];
}

try {
    $m = new Migrator($opts);
    if (isset($cliOpts['r']))
    {
        $m->clean();
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
                $ok = $m->upgradeToLatest();
            }
            else
            {
                $ok = $m->migrateToVersion($targetVersion);
            }
            exit($ok === true ? 0 : 1);
            break;
    }
} catch (Exception $e) {
    print "Error running migration: " . $e->getMessage() . "\n";
    exit(1);
}
