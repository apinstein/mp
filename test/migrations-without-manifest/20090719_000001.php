<?php
class Migration20090719_000001 extends Migration
{
    public function up()
    {
        global $testMigrationNumber, $testMigrationIncrementedNumber, $testMigrationHasRunCounter;
        $testMigrationHasRunCounter++;
        $testMigrationNumber = 1;
        $testMigrationIncrementedNumber++;
    }
    public function down()
    {
        global $testMigrationNumber, $testMigrationIncrementedNumber, $testMigrationHasRunCounter;
        $testMigrationHasRunCounter++;
        $testMigrationNumber = 0;
        $testMigrationIncrementedNumber--;
    }
    public function description()
    {
        return "Version 1";
    }
}
