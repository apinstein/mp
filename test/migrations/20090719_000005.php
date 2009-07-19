<?php
class Migration20090719_000005 extends Migration
{
    public function up()
    {
        global $testMigrationNumber, $testMigrationIncrementedNumber, $testMigrationHasRunCounter;
        $testMigrationHasRunCounter++;
        $testMigrationNumber = 5;
        $testMigrationIncrementedNumber++;
    }
    public function down()
    {
        global $testMigrationNumber, $testMigrationIncrementedNumber, $testMigrationHasRunCounter;
        $testMigrationHasRunCounter++;
        $testMigrationNumber = 4;
        $testMigrationIncrementedNumber--;
    }
    public function description()
    {
        return "Version 5";
    }
}
