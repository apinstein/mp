<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * @package WebApplication
 * @copyright Copyright (c) 2005 Alan Pinstein. All Rights Reserved.
 * @license BSD
 * @author Alan Pinstein <apinstein@mac.com>                        
 */

/**
 * An inteface describing methods used by Migrator to get/set the version of the app.
 *
 * This decoupling allows different applications to decide where they want to store their app's version information; in a file, DB, or wherever they want.
 */
interface MigratorVersionProvider
{
    public function setVersion($migrator, $v);
    public function getVersion($migrator);
}

/**
 * A MigratorVersionProvider that stores the current version in migrationsDir/versions.txt.
 */
class MigratorVersionProviderFile implements MigratorVersionProvider
{
    public function setVersion($migrator, $v)
    {
        file_put_contents($this->getVersionFilePath($migrator), $v);
    }
    public function getVersion($migrator)
    {
        $versionFile = $this->getVersionFilePath($migrator);
        $versionFileDir = dirname($versionFile);
        if (!file_exists($versionFileDir))
        {
            mkdir($versionFileDir, 0777, true);
            $this->setVersion($migrator, Migrator::VERSION_ZERO);
        }
        return file_get_contents($this->getVersionFilePath($migrator));
    }

    private function getVersionFilePath($migrator)
    {
        return $migrator->getMigrationsDirectory() . '/version.txt';
    }
}

/**
 * Abstract base class for a migration.
 *
 * Each MP "migration" is implemented by calling functions in the corresponding concrete Migration subclass.
 *
 * Subclasses must implement at least the up() and down() methods.
 */
abstract class Migration
{
    /**
     * Description of the migration.
     *
     * @return string
     */
    public function description() { return NULL; }

    /**
     * Code to migration *to* this migration.
     *
     * @param object Migrator
     * @throws object Exception If any exception is thrown the migration will be reverted.
     */
    abstract public function up($migrator);

    /**
     * Code to undo this migration.
     *
     * @param object Migrator
     * @throws object Exception If any exception is thrown the migration will be reverted.
     */
    abstract public function down($migrator);

    /**
     * Code to handle cleanup of a failed up() migration.
     *
     * @param object Migrator
     */
    public function upRollback($migrator) {}

    /**
     * Code to handle cleanup of a failed down() migration.
     *
     * @param object Migrator
     */
    public function downRollback($migrator) {}
}

/**
 * Exception that should be thrown by a {@link object Migration Migration's} down() method if the migration is irreversible (ie a one-way migration).
 */
class MigrationOneWayException extends Exception {}

abstract class MigratorDelegate
{
    /**
     * You can provide a custom {@link MigratorVersionProvider} 
     *
     * @return object MigratorVersionProvider
     */
    public function getVersionProvider() {}

    /**
     * You can provide a path to the migrations directory which holds the migrations files.
     *
     * @return string /full/path/to/migrations_dir Which ends without a trailing '/'.
     */
    public function getMigrationsDirectory() {}

    /**
     * You can implement custom "clean" functionality for your application here.
     *
     * "Clean" is called if the migrator has been requested to set up a clean environment before migrating.
     *
     * This is typically used to rebuild the app state from the ground-up.
     */
    public function clean() {}
}

class Migrator
{
    const OPT_MIGRATIONS_DIR             = 'migrationsDir';
    const OPT_VERSION_PROVIDER           = 'versionProvider';
    const OPT_DELEGATE                   = 'delegate';
    const OPT_VERBOSE                    = 'verbose';

    const DIRECTION_UP                   = 'up';
    const DIRECTION_DOWN                 = 'down';

    const VERSION_ZERO                   = '0';

    /**
     * @var string The path to the directory where migrations are stored.
     */
    protected $migrationsDirectory;
    /**
     * @var object MigratorVersionProvider
     */
    protected $versionProvider;
    /**
     * @var object MigratorDelegate
     */
    protected $delegate;
    /**
     * @var object MigratorDelegate
     */
    protected $verbose;
    /**
     * @var array An array of all migrations installed for this app.
     */
    protected $migrationsFiles = array();

    /**
     * Create a migrator instance.
     *
     * @param array Options Hash: set any of {@link Migrator::OPT_MIGRATIONS_DIR}, {@link Migrator::OPT_VERSION_PROVIDER}, {@link Migrator::OPT_DELEGATE}
     *              NOTE: values from delegate override values from the options hash.
     */
    public function __construct($opts = array())
    {
        $opts = array_merge(array(
                                Migrator::OPT_MIGRATIONS_DIR        => './migrations',
                                Migrator::OPT_VERSION_PROVIDER      => new MigratorVersionProviderFile($this),
                                Migrator::OPT_DELEGATE              => NULL,
                                Migrator::OPT_VERBOSE               => false,
                           ), $opts);

        // set up initial data
        $this->setMigrationsDirectory($opts[Migrator::OPT_MIGRATIONS_DIR]);
        $this->setVersionProvider($opts[Migrator::OPT_VERSION_PROVIDER]);
        $this->verbose = $opts[Migrator::OPT_VERBOSE];
        if ($opts[Migrator::OPT_DELEGATE])
        {
            $this->setDelegate($opts[Migrator::OPT_DELEGATE]);
        }

        // get info from delegate
        if ($this->delegate)
        {
            if (method_exists($this->delegate, 'getVersionProvider'))
            {
                $this->setVersionProvider($this->delegate->getVersionProvider());
            }
            if (method_exists($this->delegate, 'getMigrationsDirectory'))
            {
                $this->setMigrationsDirectory($this->delegate->getMigrationsDirectory());
            }
        }

        // initialize migration state
        $this->logMessage("MP - The PHP Migrator.\n");

        $this->collectionMigrationsFiles();
    }

    protected function collectionMigrationsFiles()
    {
        $this->logMessage("Looking for migrations... ", true);
        foreach (new DirectoryIterator($this->getMigrationsDirectory()) as $file) {
            if ($file->isDot()) continue;
            if ($file->isDir()) continue;
            $matches = array();
            if (preg_match('/^([0-9]{8}_[0-9]{6}).php$/', $file->getFilename(), $matches))
            {
                $this->migrationsFiles[$matches[1]] = $file->getFilename();
            }
            // sort in reverse chronological order
            natsort($this->migrationsFiles);
        }
        $this->logMessage("Found " . count($this->migrationsFiles) . " migrations.\n", true);
    }

    public function logMessage($msg, $onlyIfVerbose = false)
    {
        if (!$this->verbose && $onlyIfVerbose) return;
        print $msg;
    }
 
    public function setDelegate($d)
    {
        if (!is_object($d)) throw new Exception("setDelegate requires an object instance.");
        $this->delegate = $d;
    }

    public function getDelegate()
    {
        return $this->delegate;
    }

    public function setMigrationsDirectory($dir)
    {
        $this->migrationsDirectory = $dir;
        return $this;
    }

    public function getMigrationsDirectory()
    {
        return $this->migrationsDirectory;
    }

    public function setVersionProvider($vp)
    {
        if (!($vp instanceof MigratorVersionProvider)) throw new Exception("setVersionProvider requires an object implementing MigratorVersionProvider.");
        $this->versionProvider = $vp;
        return $this;
    }
    public function getVersionProvider()
    {
        return $this->versionProvider;
    }

    protected function findNextMigration($currentMigration)
    {
        $next = NULL;
        $reachedCurrent = ($currentMigration === Migrator::VERSION_ZERO);
        foreach ($this->migrationsFiles as $migrationName => $migrationFile) {
            if (!$reachedCurrent)
            {
                if ($migrationName === $currentMigration)
                {
                    $reachedCurrent = true;
                    continue;
                }
                continue;
            }
            $next = $migrationName;
            break;
        }
        return $next;
    }

    // ACTIONS
    /**
     * Create a migrate stub file.
     *
     * Creates a new migration file in the migrations directory with a basic template for writing a migration.
     */
    public function createMigration()
    {
        $dts = date('Ymd_His');
        $filename = $dts . '.php';
        $tpl = <<<END
<?php
class Migration{$dts} extends Migration
{
    public function up(\$migrator)
    {
    }
    public function down(\$migrator)
    {
    }
    public function description()
    {
        return "Migration created at {$dts}.";
    }
}
END;
        file_put_contents($this->getMigrationsDirectory() . "/{$filename}", $tpl);
    }

    private function instantiateMigration($migrationName)
    {
        require_once($this->getMigrationsDirectory() . "/" . $this->migrationsFiles[$migrationName]);
        $migrationClassName = "Migration{$migrationName}";
        return new $migrationClassName;
    }

    /**
     * Run the given migration as an upgrade.
     *
     * @param string The migration version.
     * @return boolean TRUE if migration ran successfully, false otherwise.
     */
    public function runUpgrade($migrationName)
    {
        $migration = $this->instantiateMigration($migrationName);
        $this->logMessage("Running upgrade: " . $migration->description() . "\n", false);
        try {
            $migration->up($this);
            $this->getVersionProvider()->setVersion($this, $migrationName);
            return true;
        } catch (Exception $e) {
            $this->logMessage("Error during migration {$migrationName}: {$e}\n");
            if (method_exists($migration, 'upRollback'))
            {
                try {
                    $migration->upRollback($this);
                } catch (Exception $e) {
                    $this->logMessage("Error during rollback of migration {$migrationName}: {$e}\n");
                }

            }
        }
        return false;
    }

    /**
     * Run the given migration as a downgrade.
     *
     * @param string The migration version.
     * @return boolean TRUE if migration ran successfully, false otherwise.
     */
    public function runDowngrade($migrationName)
    {
        $migration = $this->instantiateMigration($migrationName);
        $this->logMessage("Running downgrade: " . $migration->description() . "\n", false);
        try {
            $migration->down($this);
            $this->getVersionProvider()->setVersion($this, $migrationName);
            return true;
        } catch (Exception $e) {
            $this->logMessage("Error during migration {$migrationName}: {$e}\n");
            if (method_exists($migration, 'downRollback'))
            {
                try {
                    $migration->downRollback($this);
                } catch (Exception $e) {
                    $this->logMessage("Error during rollback of migration {$migrationName}: {$e}\n");
                }

            }
        }
        return false;
    }

    public function upgradeToVersion($toVersion)
    {
        $currentVersion = $this->getVersionProvider()->getVersion($this);
        if ($currentVersion === $toVersion)
        {
            $this->logMessage("Already at version {$currentVersion}.\n");
            return;
        }

        $this->logMessage("Updgrading from version {$currentVersion} to {$toVersion}.\n");
        while (true) {
            $nextMigration = $this->findNextMigration($currentVersion);
            if (!$nextMigration) break;

            $ok = $this->runUpgrade($nextMigration);
            if (!$ok)
            {
                break;
            }
            $currentVersion = $nextMigration;
        }
        if ($currentVersion === $toVersion)
        {
            $this->logMessage("Upgrade to {$toVersion} succeeded.\n");
        }
        else
        {
            $this->logMessage("Upgrade failed at {$currentVersion}. Current version is " . $this->getVersionProvider()->getVersion($this) . "\n");
        }
    }

    /**
     * Reset the application to "initial state" suitable for running migrations against.
     *
     * @return object Migrator
     * @see Migrator::upgradeToLatest()
     */
    public function clean()
    {
        $this->logMessage("Cleaning...\n");

        // reset version number
        $this->getVersionProvider()->setVersion($this, Migrator::VERSION_ZERO);

        // call delegate's clean
        if ($this->delegate && method_exists($this->delegate, 'clean'))
        {
            $this->delegate->clean();
        }

        return $this;
    }

    /**
     * Upgrade to the latest version of the application.
     *
     * @see Migrator::clean()
     */
    public function upgradeToLatest()
    {
        if (empty($this->migrationsFiles)) return;
        $lastMigration = array_pop(array_keys($this->migrationsFiles));
        $this->upgradeToVersion($lastMigration);
    }
}

$m = new Migrator(array('verbose' => false));
$m->clean();
$m->upgradeToLatest();
print "\n";


