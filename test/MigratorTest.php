<?php

require_once __DIR__ . '/../Migrator.php';

use PHPUnit\Framework\TestCase;

class MigratorTest extends TestCase
{
    protected $migrator;

    function setup()
    {
        $opts = array(
            Migrator::OPT_MIGRATIONS_DIR    => __DIR__ . '/migrations',
            Migrator::OPT_QUIET             => true,
        );
        $this->migrator = new Migrator($opts);
        $this->migrator->getVersionProvider()->setVersion($this->migrator, 0);  // hard-reset to version 0
    }

    function testFreshMigrationsStartAtVersionZero()
    {
        $this->assertEquals(Migrator::VERSION_ZERO, $this->migrator->getVersion());
    }

    private function assertAtVersion($version)
    {
        $this->assertEquals($version, $this->migrator->getVersion(), "At wrong version.");
    }

    private function assertAuditTrail($expectedAuditTrail)
    {
        $this->assertEquals($expectedAuditTrail, $this->migrator->getMigrationAuditTrail());
    }

    function testLatestVersion()
    {
        $this->assertEquals('20090719_000005', $this->migrator->latestVersion());
    }

    function testCleanGoesToVersionZero()
    {
        $this->migrator->clean();
        $this->assertAtVersion(Migrator::VERSION_ZERO);
        $this->assertAuditTrail(array());
    }

    function testMigratingToVersionZero()
    {
        $this->migrator->getVersionProvider()->setVersion($this->migrator, '20090719_000005');
        $this->migrator->migrateToVersion(Migrator::VERSION_ZERO);
        $this->assertAtVersion(Migrator::VERSION_ZERO);
        $this->assertAuditTrail(array(
            '20090719_000005:down',
            '20090719_000003:down',
            '20090719_000004:down',
            '20090719_000002:down',
            '20090719_000001:down',
            ));
    }

    function testMigratingToHead()
    {
        $this->migrator->migrateToVersion(Migrator::VERSION_HEAD);
        $this->assertAtVersion('20090719_000005');
        $this->assertAuditTrail(array(
            '20090719_000001:up',
            '20090719_000002:up',
            '20090719_000004:up',
            '20090719_000003:up',
            '20090719_000005:up',
        ));
    }

    function testMigrateUp()
    {
        // mock out migrator; make sure UP calls migrate to appropriate version
        $this->migrator->getVersionProvider()->setVersion($this->migrator, '20090719_000001');

        $this->migrator->migrateToVersion(Migrator::VERSION_UP);
        $this->assertAtVersion('20090719_000002');
        $this->assertAuditTrail(array(
            '20090719_000002:up'
        ));
    }

    function testMigrateDown()
    {
        $this->migrator->getVersionProvider()->setVersion($this->migrator, '20090719_000002');
        $this->migrator->migrateToVersion(Migrator::VERSION_DOWN);
        $this->assertAtVersion('20090719_000001');
        $this->assertAuditTrail(array(
            '20090719_000002:down'
        ));
    }

    function testMigratingToCurrentVersionRunsNoMigrations()
    {
        $this->migrator->getVersionProvider()->setVersion($this->migrator, '20090719_000002');
        $this->migrator->migrateToVersion('20090719_000002');
        $this->assertAtVersion('20090719_000002');
        $this->assertAuditTrail(array());
    }

    function testMigrateToVersion1()
    {
        $this->migrator->migrateToVersion('20090719_000001');
        $this->assertAtVersion('20090719_000001');
        $this->assertAuditTrail(array(
            '20090719_000001:up',
        ));
    }

    function testMigrateToVersion2()
    {
        $this->migrator->migrateToVersion('20090719_000002');
        $this->assertAtVersion('20090719_000002');
        $this->assertAuditTrail(array(
            '20090719_000001:up',
            '20090719_000002:up',
        ));
    }

    function testMigrateToVersion4()
    {
        $this->migrator->migrateToVersion('20090719_000004');
        $this->assertAtVersion('20090719_000004');
        $this->assertAuditTrail(array(
            '20090719_000001:up',
            '20090719_000002:up',
            '20090719_000004:up',
        ));
    }

    function testMigrateToVersion3()
    {
        $this->migrator->migrateToVersion('20090719_000003');
        $this->assertAtVersion('20090719_000003');
        $this->assertAuditTrail(array(
            '20090719_000001:up',
            '20090719_000002:up',
            '20090719_000004:up',
            '20090719_000003:up',
        ));
    }

    function testMigrateToVersion5()
    {
        $this->migrator->migrateToVersion('20090719_000005');
        $this->assertAtVersion('20090719_000005');
        $this->assertAuditTrail(array(
            '20090719_000001:up',
            '20090719_000002:up',
            '20090719_000004:up',
            '20090719_000003:up',
            '20090719_000005:up',
        ));
    }
}
