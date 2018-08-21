<?php

require_once __DIR__.'/../Migrator.php';

use PHPUnit\Framework\TestCase;

class MigratorManifestUpgradeTest extends TestCase
{
    private function migrationsDir()
    {
        return __DIR__.'/migrations-without-manifest';
    }

    private function manifestFile()
    {
        return $this->migrationsDir().'/migrations.json';
    }

    public function teardown()
    {
        @unlink($this->manifestFile());
    }

    public function testMPRequiresManifestFile()
    {
        $this->expectException('MigrationNoManifestException');
        new Migrator(array(
            Migrator::OPT_MIGRATIONS_DIR => $this->migrationsDir(),
            Migrator::OPT_QUIET => true,
        ));
    }

    public function testMigrationUpgradeCreatesExpectedManifestFile()
    {
        $this->assertFileNotExists($this->manifestFile(), "Shouldn't be a manifest file yet.");

        new Migrator(array(
            Migrator::OPT_MIGRATIONS_DIR => $this->migrationsDir(),
            Migrator::OPT_QUIET => true,
            Migrator::OPT_OFFER_MANIFEST_UPGRADE => true,
        ));

        $this->assertFileExists($this->manifestFile(), 'Should be a manifest file now.');
        $this->assertEquals(array(
            '20090719_000001',
            '20090719_000002',
            '20090719_000003',
            '20090719_000004',
            '20090719_000005',
        ), json_decode(file_get_contents($this->manifestFile())));
    }
}
