#!/opt/local/bin/php
<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 syntax=php: */
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

$shortopts .= "h";  // -h --help => print help
$longopts[] = "help";

$cliOpts = getopt($shortopts, $longopts);

if (isset($cliOpts['h']))
{
    print <<<END
migrate usage:
  -c --command [migrate|create|clean]
     migrate options:
        -t --target <TargetVersion>
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
if (isset($cliOpts['d']))
{
    $opts[Migrator::OPT_MIGRATIONS_DIR] = $cliOpts['d'];
}
if (isset($cliOpts['x']))
{
    $opts[Migrator::OPT_PDO_DSN] = $cliOpts['x'];
}

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

