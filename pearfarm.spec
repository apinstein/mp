<?php

$spec = Pearfarm_PackageSpec::create(array(Pearfarm_PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
            ->setName('mp')
            ->setChannel('apinstein.pearfarm.org')
            ->setSummary('MP: Migrations for PHP')
            ->setDescription('A generic db migrations engine for PHP.')
            ->setReleaseVersion('1.0.2')
            ->setReleaseStability('stable')
            ->setApiVersion('1.0.0')
            ->setApiStability('stable')
            ->setLicense(Pearfarm_PackageSpec::LICENSE_MIT)
            ->setNotes('See http://github.com/apinstein/mp.')
            ->addMaintainer('lead', 'Alan Pinstein', 'apinstein', 'apinstein@mac.com')
            ->addGitFiles()
            ->addExecutable('mp')
            ;
