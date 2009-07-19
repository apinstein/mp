<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
/**
 * @package Migrator
 * @copyright Copyright (c) 2005 Alan Pinstein. All Rights Reserved.
 * @author Alan Pinstein <apinstein@mac.com>                        
 * 
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
 *
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
        if (!file_exists($versionFile))
        {
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
    protected $migrator = NULL;

    public function __construct($migrator)
    {
        $this->migrator = $migrator;
    }

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
    abstract public function up();

    /**
     * Code to undo this migration.
     *
     * @param object Migrator
     * @throws object Exception If any exception is thrown the migration will be reverted.
     */
    abstract public function down();

    /**
     * Code to handle cleanup of a failed up() migration.
     *
     * @param object Migrator
     */
    public function upRollback() {}

    /**
     * Code to handle cleanup of a failed down() migration.
     *
     * @param object Migrator
     */
    public function downRollback() {}
}

/**
 * Exception that should be thrown by a {@link object Migration Migration's} down() method if the migration is irreversible (ie a one-way migration).
 */
class MigrationOneWayException extends Exception {}
class MigrationUnknownVersionException extends Exception {}

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
     *
     * @param object Migrator
     */
    public function clean($migrator) {}
}

class Migrator
{
    const OPT_MIGRATIONS_DIR             = 'migrationsDir';
    const OPT_VERSION_PROVIDER           = 'versionProvider';
    const OPT_DELEGATE                   = 'delegate';
    const OPT_PDO_DSN                    = 'dsn';
    const OPT_VERBOSE                    = 'verbose';
    const OPT_QUIET                      = 'quiet';

    const DIRECTION_UP                   = 'up';
    const DIRECTION_DOWN                 = 'down';

    const VERSION_ZERO                   = '0';
    const VERSION_UP                     = 'up';
    const VERSION_DOWN                   = 'down';

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
     * @var object PDO A PDO connection.
     */
    protected $dbCon;
    /**
     * @var boolean TRUE to set verbose logging
     */
    protected $verbose;
    /**
     * @var boolean TRUE to supress all logging.
     */
    protected $quiet;
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
                                Migrator::OPT_PDO_DSN               => NULL,
                                Migrator::OPT_VERBOSE               => false,
                                Migrator::OPT_QUIET                 => false,
                           ), $opts);

        // set up initial data
        $this->setMigrationsDirectory($opts[Migrator::OPT_MIGRATIONS_DIR]);
        $this->setVersionProvider($opts[Migrator::OPT_VERSION_PROVIDER]);
        $this->verbose = $opts[Migrator::OPT_VERBOSE];
        if ($opts[Migrator::OPT_DELEGATE])
        {
            $this->setDelegate($opts[Migrator::OPT_DELEGATE]);
        }
        if ($opts[Migrator::OPT_PDO_DSN])
        {
            $this->dbCon = new PDO($opts[Migrator::OPT_PDO_DSN]);
            $this->dbCon->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $this->quiet = $opts[Migrator::OPT_QUIET];

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

        $this->initializeMigrationsDir();

        // initialize migration state
        $this->logMessage("MP - The PHP Migrator.\n");

        $this->collectionMigrationsFiles();
    }

    protected function initializeMigrationsDir()
    {
        // initialize migrations dir
        $migrationsDir = $this->getMigrationsDirectory();
        if (!file_exists($migrationsDir))
        {
            mkdir($migrationsDir, 0777, true);
            $cleanTPL = <<<END
<?php

class MigrateClean
{
    public function clean(\$migrator)
    {
        // hard-reset your app to a clean slate
    }
}
END;
            file_put_contents($migrationsDir . '/clean.php', $cleanTPL);
            $this->getVersionProvider()->setVersion($this, Migrator::VERSION_ZERO);
        }
    }

    public function getVersion()
    {
        return $this->getVersionProvider()->getVersion($this);
    }

    protected function collectionMigrationsFiles()
    {
        $this->logMessage("Looking for migrations...\n", true);
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
        $this->logMessage("Found " . count($this->migrationsFiles) . " migrations:" . print_r($this->migrationsFiles, true), true);
    }

    public function logMessage($msg, $onlyIfVerbose = false)
    {
        if ($this->quiet) return;
        if (!$this->verbose && $onlyIfVerbose) return;
        print $msg;
    }

    public function getDbCon()
    {
        return $this->dbCon;
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

    protected function indexOfVersion($findVersion)
    {
        // normal logic for when there is 1+ migrations and we aren't at VERSION_ZERO
        $foundCurrent = false;
        $currentIndex = 0;
        foreach (array_keys($this->migrationsFiles) as $version) {
            if ($version === $findVersion)
            {
                $foundCurrent = true;
                break;
            }
            $currentIndex++;
        }
        if (!$foundCurrent)
        {
            throw new MigrationUnknownVersionException("Version {$findVersion} is not a known migration.");
        }
        return $currentIndex;
    }

    /**
     * Find the next migration to run in the given direction.
     *
     * @param string Current version
     * @param string Direction (one of Migrator::DIRECTION_UP or Migrator::DIRECTION_DOWN).
     * @return string The migration name of the "next" migration in the correct direction, or NULL if there is no "next" migration in that direction.
     * @throws
     */
    protected function findNextMigration($currentMigration, $direction)
    {
        // special case when no migrations exist
        if (count($this->migrationsFiles) === 0) return NULL;

        $migrationVersions = array_keys($this->migrationsFiles);

        // special case when current == VERSION_ZERO
        if ($currentMigration === Migrator::VERSION_ZERO)
        {
            if ($direction === Migrator::DIRECTION_UP)
            {
                return $migrationVersions[0];
            }
            else
            {
                return NULL;    // no where down from VERSION_ZERO
            }
        }

        // normal logic for when there is 1+ migrations and we aren't at VERSION_ZERO
        $currentIndex = $this->indexOfVersion($currentMigration);
        if ($direction === Migrator::DIRECTION_UP)
        {
            $lastIndex = count($migrationVersions) - 1;
            if ($currentIndex === $lastIndex)
            {
                return NULL;
            }
            return $migrationVersions[$currentIndex + 1];
        }
        else
        {
            if ($currentIndex === 0)
            {
                return NULL;
            }
            return $migrationVersions[$currentIndex - 1];
        }
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
    public function up()
    {
    }
    public function down()
    {
    }
    public function description()
    {
        return "Migration created at {$dts}.";
    }
}
END;
        $filePath = $this->getMigrationsDirectory() . "/{$filename}";
        if (file_exists($filePath)) throw new Exception("Migration {$dts} already exists! Aborting.");
        file_put_contents($filePath, $tpl);
        $this->logMessage("Created migration {$dts} at {$filePath}.\n");
    }

    private function instantiateMigration($migrationName)
    {
        require_once($this->getMigrationsDirectory() . "/" . $this->migrationsFiles[$migrationName]);
        $migrationClassName = "Migration{$migrationName}";
        return new $migrationClassName($this);
    }

    /**
     * Run the given migration in the specified direction.
     *
     * @param string The migration version.
     * @param string Direction.
     * @return boolean TRUE if migration ran successfully, false otherwise.
     */
    public function runMigration($migrationName, $direction)
    {
        if ($direction === Migrator::DIRECTION_UP)
        {
            $info = array(
                'actionName'        => 'Upgrade',
                'migrateF'          => 'up',
                'migrateRollbackF'  => 'upRollback',
            );
        }
        else
        {
            $info = array(
                'actionName'        => 'Downgrade',
                'migrateF'          => 'down',
                'migrateRollbackF'  => 'downRollback',
            );
        }
        $migration = $this->instantiateMigration($migrationName);
        $this->logMessage("Running {$migrationName} {$info['actionName']}: " . $migration->description() . "\n", false);
        try {
            $migration->$info['migrateF']($this);
            if ($direction === Migrator::DIRECTION_UP)
            {
                $this->getVersionProvider()->setVersion($this, $migrationName);
            }
            else
            {
                $downgradedToVersion = $this->findNextMigration($migrationName, Migrator::DIRECTION_DOWN);
                $this->getVersionProvider()->setVersion($this, ($downgradedToVersion === NULL ? Migrator::VERSION_ZERO : $downgradedToVersion));
            }
            return true;
        } catch (Exception $e) {
            $this->logMessage("Error during {$info['actionName']} migration {$migrationName}: {$e}\n");
            if (method_exists($migration, $info['migrateRollbackF']))
            {
                try {
                    $migration->$info['migrateRollbackF']($this);
                } catch (Exception $e) {
                    $this->logMessage("Error during rollback of {$info['actionName']} migration {$migrationName}: {$e}\n");
                }

            }
        }
        return false;
    }

    /**
     * Run the given migration as an upgrade.
     *
     * @param string The migration version.
     * @return boolean TRUE if migration ran successfully, false otherwise.
     */
    public function runUpgrade($migrationName)
    {
        return $this->runMigration($migrationName, Migrator::DIRECTION_UP);
    }

    /**
     * Run the given migration as a downgrade.
     *
     * @param string The migration version.
     * @return boolean TRUE if migration ran successfully, false otherwise.
     */
    public function runDowngrade($migrationName)
    {
        return $this->runMigration($migrationName, Migrator::DIRECTION_DOWN);
    }

    /**
     * Migrate to the specified version.
     *
     * @param string The Version.
     * @return boolean TRUE if migration successfully ended on specified version.
     */
    public function migrateToVersion($toVersion)
    {
        $this->logMessage("\n");

        $currentVersion = $this->getVersionProvider()->getVersion($this);
        if ($currentVersion === $toVersion)
        {
            $this->logMessage("Already at version {$currentVersion}.\n");
            return true;
        }

        // unroll meta versions
        if ($toVersion === Migrator::VERSION_UP)
        {
            $toVersion = $this->findNextMigration($currentVersion, Migrator::DIRECTION_UP);
        }
        else if ($toVersion === Migrator::VERSION_DOWN)
        {
            $toVersion = $this->findNextMigration($currentVersion, Migrator::DIRECTION_DOWN);
        }

        // verify target version
        if ($toVersion !== Migrator::VERSION_ZERO)
        {
            try {
                $this->indexOfVersion($toVersion);
            } catch (MigrationUnknownVersionException $e) {
                $this->logMessage("Cannot migrate to version {$toVersion} because it does not exist.\n");
                return false;
            }
        }
        // verify current version
        try {
            if ($currentVersion !== Migrator::VERSION_ZERO)
            {
                $currentVersionIndex = $this->indexOfVersion($currentVersion);
            }
        } catch (MigrationUnknownVersionException $e) {
            $this->logMessage("Cannot validate existing version {$currentVersion} because it does not exist.\n");
        }

        // calculate direction
        if ($currentVersion === Migrator::VERSION_ZERO)
        {
            $direction = Migrator::DIRECTION_UP;
        }
        else if ($currentVersion === array_pop(array_keys($this->migrationsFiles)))
        {
            $direction = Migrator::DIRECTION_DOWN;
        }
        else if ($toVersion === Migrator::VERSION_ZERO)
        {
            $direction = Migrator::DIRECTION_DOWN;
        }
        else
        {
            $currentVersionIndex = $this->indexOfVersion($currentVersion);
            $toVersionIndex = $this->indexOfVersion($toVersion);
            $direction = ($toVersionIndex > $currentVersionIndex ? Migrator::DIRECTION_UP : Migrator::DIRECTION_DOWN);
        }

        $actionName = ($direction === Migrator::DIRECTION_UP ? 'Upgrade' : 'Downgrade');
        $this->logMessage("{$actionName} from version {$currentVersion} to {$toVersion}.\n");
        while ($currentVersion !== $toVersion) {
            if ($direction === Migrator::DIRECTION_UP)
            {
                $nextMigration = $this->findNextMigration($currentVersion, $direction);
                if (!$nextMigration) break;

                $ok = $this->runMigration($nextMigration, Migrator::DIRECTION_UP);
                if (!$ok)
                {
                    break;
                }
            }
            else
            {
                $nextMigration = $this->findNextMigration($currentVersion, Migrator::DIRECTION_DOWN);
                $ok = $this->runMigration($currentVersion, $direction);
                if (!$ok)
                {
                    break;
                }
                if (!$nextMigration)
                {
                    // next is 0, we are done!
                    $currentVersion = $nextMigration = Migrator::VERSION_ZERO;
                }
            }
            $currentVersion = $nextMigration;
            $this->logMessage("Current version now {$currentVersion}\n", true);
        }
        if ($currentVersion === $toVersion)
        {
            $this->logMessage("{$toVersion} {$actionName} succeeded.\n");
            return true;
        }
        else
        {
            $this->logMessage("{$toVersion} {$actionName} failed.\nRolled back to " . $this->getVersionProvider()->getVersion($this) . ".\n");
            return false;
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
            $this->delegate->clean($this);
        }
        else
        {
            // look for migrations/clean.php, className = MigrateClean::clean()
            $cleanFile = $this->getMigrationsDirectory() . '/clean.php';
            if (file_exists($cleanFile))
            {
                require_once($cleanFile);
                MigrateClean::clean($this);
            }
        }

        return $this;
    }

    /**
     * Upgrade to the latest version of the application.
     *
     * @see Migrator::clean()
     * @return boolean TRUE if migration successfully ended at latest version.
     */
    public function upgradeToLatest()
    {
        if (empty($this->migrationsFiles))
        {
            $this->logMessage("No migrations available.\n");
            return true;
        }
        $lastMigration = array_pop(array_keys($this->migrationsFiles));
        return $this->migrateToVersion($lastMigration, Migrator::DIRECTION_UP);
    }
}
