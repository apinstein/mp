<?php

class VCSHelper
{
    protected $vcsDelegate;

    public function __construct($vcsDelegate)
    {
        if (!($vcsDelegate instanceof VCSHelper_delegate)) throw new Exception("VCSHelper_delegate required.");
        $this->vcsDelegate = $vcsDelegate;
    }

    public static function create($vcsDelegate)
    {
        return new VCSHelper($vcsDelegate);
    }

    public function run($mp, $fromBranch = NULL, $migrationCommandPrefix = "[mp]")
    {
        $currentMigration = $mp->getVersion();

        // find last branch
        if (!$fromBranch)
        {
            $fromBranch = $this->vcsDelegate->lastBranch();
        }
        $fromBranchPretty = $this->vcsDelegate->prettyRefs($fromBranch);

        // hack for now
        $migrationCommandPrefix = escapeshellarg($_SERVER['argv'][0]) . ' ' . escapeshellarg($_SERVER['argv'][1]);

        // find current branch
        $toBranch = $this->vcsDelegate->head();
        $toBranchPretty = $this->vcsDelegate->prettyRefs($toBranch);

        print "\n\n**** EXPLORING BRANCHES ****\n";
        print "\n\nMigration check for move from:\n\t{$fromBranch} {$fromBranchPretty}\nto\n\t{$toBranch} {$toBranchPretty}\n";

        // find common ancestor
        $commonAncestor = $this->vcsDelegate->commonAncestor($fromBranch, $toBranch);
        $commonAncestorPretty = $this->vcsDelegate->prettyRefs($commonAncestor);
        print "\nCommon ancestor is:\n\t{$commonAncestor} {$commonAncestorPretty}\n";

        // find last migration on common ancestor
        $sharedMigrations = $this->vcsDelegate->migrationsForBranch($commonAncestor);
        $lastSharedMigration = $this->lastMigrationFromArray($sharedMigrations);
        print "\nMost recent migration on both branches is {$lastSharedMigration}.\n";

        // find last migration on from branch
        $fromBranchMigrations = $this->vcsDelegate->migrationsForBranch($fromBranch);
        $lastFromMigration = $this->lastMigrationFromArray($fromBranchMigrations);
        print "Last migration on {$fromBranch}{$fromBranchPretty} is {$lastFromMigration}.\n";

        // find last migration on to branch
        $toBranchMigrations = $this->vcsDelegate->migrationsForBranch($toBranch);
        $lastToMigration = $this->lastMigrationFromArray($toBranchMigrations);

        // calculate the list of resequencing
        print "\n\n**** CALCULATING RESEQUENCING ****\n";
        // @todo refactor the calculation of which migrations are needed into a separate function
        $resequenceBase = $this->migrationNameAfterMigration($lastFromMigration);

        $onlyOnToBranch = array_diff($toBranchMigrations, $sharedMigrations);
        $migrationToBeat = $lastFromMigration;
        $resequenceOperations = array();
        foreach ($onlyOnToBranch as $migration) {
            if ($this->compareMigrations($migration, $migrationToBeat) === -1)
            {
                // needs resequence
                $resequencedMigration = $this->migrationNameAfterMigration($migrationToBeat);
                print "Need to reseqeunce {$migration} to {$resequencedMigration}\n";
                $migrationToBeat = $resequencedMigration;

                $resequenceOperations[] = "git mv migrations/{$migration}.php migrations/{$resequencedMigration}.php && sed -i -e 's/{$migration}/{$resequencedMigration}/g' migrations/{$resequencedMigration}.php";
            }
            else
            {
                print "Migration {$migration} doesn't need re-sequencing.\n";
            }
        }

        // what do we need to run to get things where the app is in a sane state...
        $stuffToDo = array();
        if ($resequenceOperations)
        {
            $stuffToDo["Do you want to re-sequence the migrations on {$toBranch} to run after those from {$fromBranch}?"] = array('resequenceMigrations', $resequenceOperations);
        }

        // do we need to run any migrations?
        print "\n\n**** CALCULATING MIGRATION PROCEDURES ****\n";
        if ($this->compareMigrations($currentMigration, $lastToMigration) !== 0)
        {
            print "Migrations need to be run...\n";
            $stuffToDo["Do you want to migrate the database from {$currentMigration} to {$lastToMigration}?"] = array('migrateAcrossBranches', $currentMigration,  $migrationCommandPrefix, $lastSharedMigration, $fromBranch, $lastFromMigration, $toBranch, $lastToMigration);
        }
        else
        {
            print "No migrations needed.\n";
        }

        print "\n\n**** EXECUTING BRANCH SWITCH ****\n";
        if ($stuffToDo)
        {
            foreach ($stuffToDo as $prompt => $callInfo) {
                if ($this->confirm($prompt))
                {
                    $f = array_shift($callInfo);
                    call_user_func_array(array($this, $f), $callInfo);
                }
            }
        }
        else
        {
            print "Nothing needs to be done, you're all set to code away!\n";
        }
    }

    function resequenceMigrations($resequenceOperations)
    {
        print_r($resequenceOperations);
    }
    function migrateAcrossBranches($currentMigration, $migrationCommandPrefix, $lastSharedMigration, $fromBranch, $lastFromMigration, $toBranch, $lastToMigration)
    {
        // any migration needed?
        if ($this->compareMigrations($currentMigration, $lastToMigration) !== 0)
        {
            // migrate down needed?
            if ($this->compareMigrations($currentMigration, $lastSharedMigration) === 1)
            {
                print "\n\nFrom branch is on migration {$lastFromMigration}, but last shared migration is {$lastSharedMigration}. Need to migrate down...\n";
                $this->checkoutAndMigrateTo($migrationCommandPrefix, $fromBranch, $lastSharedMigration);
            }

            print "\n\nMigrate to current branch/db version.\n";
            $this->checkoutAndMigrateTo($migrationCommandPrefix, $toBranch, $lastToMigration);
        }
        print "\n\nReturn to original branch:\n\tgit checkout {$this->vcsDelegate->branchNameForRef($toBranch)}\n";
    }
    function checkoutAndMigrateTo($migrationCommandPrefix, $checkoutBranch, $migrateTo)
    {
        print "1. Checkout {$checkoutBranch} {$this->vcsDelegate->prettyRefs($checkoutBranch)}\n";
        print "2. Migrate to {$migrateTo}\n";
        print "\tgit checkout {$checkoutBranch} && {$migrationCommandPrefix} -m{$migrateTo}\n";
    }

    function confirm($msg)
    {
        print "{$msg} (y to continue): ";
        return trim(fgets(STDIN)) === 'y';
    }

    function lastMigrationFromArray($migrations)
    {
        if (count($migrations) === 0) return NULL;
        $lastI = count($migrations) - 1; 
        return $migrations[$lastI];
    }
    function migrationNameAfterMigration($migration)
    {
        $dts = strtotime(str_replace('_', ' ', $migration));
        if (!$dts) throw new Exception("couldn't parse $migration.");

        // is last migration from today? if so, bump dts by 1 s
        if (strtotime('today') < $dts)
        {
            $resequencedDts = $dts + 1;
        }
        // otherwise, use today_00000N
        else
        {
            $resequencedDts = strtotime('today') + 1;
        }
        return date('Ymd_His', $resequencedDts);
    }

    function compareMigrations($a, $b)
    {
        // normalize & assert
        $tA = strtotime(str_replace('_', ' ', $a));
        $tB = strtotime(str_replace('_', ' ', $b));
        if (!$tA) throw new Exception("couldn't parse {$a}");
        if (!$tB) throw new Exception("couldn't parse {$b}");

        // compare
        if ($tA === $tB) return 0;
        return ($tA < $tB) ? -1 : 1;
    }
}

interface VCSHelper_delegate
{
    public function head();
    public function lastBranch();
    public function commonAncestor($a, $b);
    public function prettyRefs($ref);
    public function branchNameForRef($ref);
    public function migrationsForBranch($branch);
}

class VCSHelper_git implements VCSHelper_delegate
{
    public function head()
    {
        $cmd = "git rev-parse HEAD 2>&1";
        $commonAncestor = `$cmd`;
        return trim($commonAncestor);
    }
    public function lastBranch()
    {
        $cmd = "git reflog -1 'HEAD@{1}' | cut -f1 -d ' '";
        $lastBranch = `$cmd`;
        return trim($lastBranch);
    }
    public function prettyRefs($ref)
    {
        $cmd = "git log -1 --pretty=format:'%d' {$ref}";
        $lastBranch = `$cmd`;
        return trim($lastBranch);
    }
    public function branchNameForRef($ref)
    {
        $cmd = "git name-rev --refs='refs/heads/*' {$ref} | cut -f 2 -d ' '";
        $branchName = `$cmd`;
        return trim($branchName);
    }
    public function commonAncestor($a, $b)
    {
        $cmd = "git merge-base {$a} {$b} 2>&1";
        $commonAncestor = `$cmd`;
        return trim($commonAncestor);
    }
    public function migrationsForBranch($branch)
    {
        $cmd = "git ls-tree {$branch} --name-only -r -- migrations | sed -n -e 's/migrations.\([0-9_]\+\).*/\\1/gp' 2>&1";
        $migrations = explode("\n", `$cmd`);            // split lines into array
        $migrations = array_map('trim', $migrations);   // trim whitespace
        $migrations = array_filter($migrations);        // remove empty lines
        return $migrations;
    }
}
