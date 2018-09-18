<?php

use PHPUnit\Framework\TestCase;

class MigratorYearDirsTest extends TestCase
{
    protected $migrator;

    public function setup()
    {
        $opts = array(
            Migrator::OPT_MIGRATIONS_DIR => __DIR__.'/migrations-with-year-dirs',
            Migrator::OPT_QUIET => true,
        );
        $this->migrator = new Migrator($opts);
        $this->migrator->getVersionProvider()->setVersion($this->migrator, 0);  // hard-reset to version 0
    }

    public function testFreshMigrationsStartAtVersionZero()
    {
        $this->assertEquals(Migrator::VERSION_ZERO, $this->migrator->getVersion());
    }

    private function assertAtVersion($version)
    {
        $this->assertEquals($version, $this->migrator->getVersion(), 'At wrong version.');
    }

    private function assertAuditTrail($expectedAuditTrail)
    {
        $this->assertEquals($expectedAuditTrail, $this->migrator->getMigrationAuditTrail());
    }

    public function testLatestVersion()
    {
        $this->assertEquals('20180918_000001', $this->migrator->latestVersion());
    }

    public function testCleanGoesToVersionZero()
    {
        $this->migrator->clean();
        $this->assertAtVersion(Migrator::VERSION_ZERO);
        $this->assertAuditTrail(array());
    }

    public function testMigratingToVersionZero()
    {
        $this->migrator->getVersionProvider()->setVersion($this->migrator, '20180918_000001');
        $this->migrator->migrateToVersion(Migrator::VERSION_ZERO);
        $this->assertAtVersion(Migrator::VERSION_ZERO);
        $this->assertAuditTrail(array(
            '20180918_000001:down',
            '20170918_000002:down',
            '20170918_000001:down',
        ));
    }

    public function testMigratingToHead()
    {
        $this->migrator->migrateToVersion(Migrator::VERSION_HEAD);
        $this->assertAtVersion('20180918_000001');
        $this->assertAuditTrail(array(
            '20170918_000001:up',
            '20170918_000002:up',
            '20180918_000001:up',
        ));
    }

    public function testMigrateUp()
    {
        // mock out migrator; make sure UP calls migrate to appropriate version
        $this->migrator->getVersionProvider()->setVersion($this->migrator, '20170918_000001');

        $this->migrator->migrateToVersion(Migrator::VERSION_UP);
        $this->assertAtVersion('20170918_000002');
        $this->assertAuditTrail(array(
            '20170918_000002:up',
        ));
    }

    public function testMigrateDown()
    {
        $this->migrator->getVersionProvider()->setVersion($this->migrator, '20180918_000001');
        $this->migrator->migrateToVersion(Migrator::VERSION_DOWN);
        $this->assertAtVersion('20170918_000002');
        $this->assertAuditTrail(array(
            '20180918_000001:down',
        ));
    }

    public function testMigratingToCurrentVersionRunsNoMigrations()
    {
        $this->migrator->getVersionProvider()->setVersion($this->migrator, '20180918_000001');
        $this->migrator->migrateToVersion('20180918_000001');
        $this->assertAtVersion('20180918_000001');
        $this->assertAuditTrail(array());
    }

    public function testMigrateToVersion1()
    {
        $this->migrator->migrateToVersion('20170918_000001');
        $this->assertAtVersion('20170918_000001');
        $this->assertAuditTrail(array(
            '20170918_000001:up',
        ));
    }

    public function testMigrateToVersion2()
    {
        $this->migrator->migrateToVersion('20180918_000001');
        $this->assertAtVersion('20180918_000001');
        $this->assertAuditTrail(array(
            '20170918_000001:up',
            '20170918_000002:up',
            '20180918_000001:up',
        ));
    }
}
