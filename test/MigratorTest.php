<?php

require_once 'Migrator.php';

define('TEST_MIGRATIONS_DIR', './test/migrations');

$testMigrationNumber = 0;
$testMigrationIncrementedNumber = 0;

class MigratorTest extends PHPUnit_Framework_TestCase
{
    protected $migrator;

    function setup()
    {
        $opts = array(
            Migrator::OPT_MIGRATIONS_DIR => TEST_MIGRATIONS_DIR,
            Migrator::OPT_QUIET => true,
        );
        $this->migrator = new Migrator($opts);
        $this->migrator->getVersionProvider()->setVersion($this->migrator, 0);  // hard-reset to version 0

        global $testMigrationNumber, $testMigrationIncrementedNumber;
        $testMigrationNumber = 0;
        $testMigrationIncrementedNumber = 0;
    }

    function testFreshMigrationsStartAtVersionZero()
    {
        $this->assertEquals(Migrator::VERSION_ZERO, $this->migrator->getVersion());
    }

    private function assertAtVersion($version, $counter)
    {
        global $testMigrationNumber, $testMigrationIncrementedNumber;
        $this->assertEquals($version, $this->migrator->getVersion(), "At wrong version #");
        $this->assertEquals($counter, $testMigrationNumber, "testMigrationNumber wrong");
        $this->assertEquals($counter, $testMigrationIncrementedNumber, "testMigrationIncrementedNumber wrong");
    }

    function testLatestVersion()
    {
        $this->assertEquals('20090719_000005', $this->migrator->latestVersion());
    }

    function testCleanGoesToVersionZero()
    {
        $this->migrator->clean();
        $this->assertAtVersion(Migrator::VERSION_ZERO, 0);
    }

    function testMigratingToVersionZero()
    {
        $this->migrator->migrateToVersion(Migrator::VERSION_HEAD);
        $this->migrator->migrateToVersion(Migrator::VERSION_ZERO);
        $this->assertAtVersion(Migrator::VERSION_ZERO, 0);
    }

    function testMigratingToHead()
    {
        $this->migrator->migrateToVersion(Migrator::VERSION_HEAD);
        $this->assertAtVersion('20090719_000005', 5);
    }

    function testMigrateUp()
    {
        // mock out migrator; make sure UP calls migrate to appropriate version
        $this->migrator->migrateToVersion('20090719_000002');
//        $mock = $this->getMock($this->migrator);
//        $mock->expects($this->once())
//                        ->method('migrateToVersion')
//                        ->with($this->equalTo('20090719_000003'));
//        $this->migrator->migrateToVersion(Migrator::VERSION_UP);

          $this->migrator->migrateToVersion(Migrator::VERSION_UP);
          $this->assertAtVersion('20090719_000003', 3);
    }

    function testMigrateDown()
    {
        // mock out migrator; make sure UP calls migrate to appropriate version
        $this->migrator->migrateToVersion('20090719_000002');
//        $mock = $this->getMock($this->migrator);
//        $mock->expects($this->once())
//                        ->method('migrateToVersion')
//                        ->with($this->equalTo('20090719_000001'));
//        $this->migrator->migrateToVersion(Migrator::VERSION_DOWN);

        $this->migrator->migrateToVersion(Migrator::VERSION_DOWN);
        $this->assertAtVersion('20090719_000001', 1);
    }

    function testMigrateToVersion1()
    {
        $this->migrator->migrateToVersion('20090719_000001');
        $this->assertAtVersion('20090719_000001', 1);
    }

    function testMigrateToVersion2()
    {
        $this->migrator->migrateToVersion('20090719_000002');
        $this->assertAtVersion('20090719_000002', 2);
    }

    function testMigrateToVersion3()
    {
        $this->migrator->migrateToVersion('20090719_000003');
        $this->assertAtVersion('20090719_000003', 3);
    }

    function testMigrateToVersion4()
    {
        $this->migrator->migrateToVersion('20090719_000004');
        $this->assertAtVersion('20090719_000004', 4);
    }

    function testMigrateToVersion5()
    {
        $this->migrator->migrateToVersion('20090719_000005');
        $this->assertAtVersion('20090719_000005', 5);
    }
}
