<?php

require_once __DIR__ . '/../Migrator.php';

class MigratorManifestUpgradeTest extends PHPUnit_Framework_TestCase
{
    private function migrationsDir()
    {
        return __DIR__ . '/migrations-without-manifest';
    }
    private function manifestFile()
    {
        return $this->migrationsDir() . '/migrations.json';
    }

    function teardown()
    {
        @unlink($this->manifestFile());
    }

    function testMPRequiresManifestFile()
    {
        $this->setExpectedException('MigrationNoManifestException');
        new Migrator(array(
            Migrator::OPT_MIGRATIONS_DIR    => $this->migrationsDir(),
            Migrator::OPT_QUIET             => true,
        ));
    }

    function testMigrationUpgradeCreatesExpectedManifestFile()
    {
        $this->assertFalse(file_exists($this->manifestFile()), "Shouldn't be a manifest file yet.");

        new Migrator(array(
            Migrator::OPT_MIGRATIONS_DIR                => $this->migrationsDir(),
            Migrator::OPT_QUIET                         => true,
            Migrator::OPT_OFFER_MANIFEST_UPGRADE        => true,
        ));

        $this->assertTrue(file_exists($this->manifestFile()), "Should be a manifest file now.");
        $this->assertEquals(array(
            "20090719_000001",
            "20090719_000002",
            "20090719_000003",
            "20090719_000004",
            "20090719_000005",
        ), json_decode(file_get_contents($this->manifestFile())));
    }
}
