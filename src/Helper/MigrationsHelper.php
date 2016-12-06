<?php

namespace Etre\Shell\Helper;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

class MigrationsHelper {
    /**
     * @param InputInterface $input
     */
    public function initEtreConfig(InputInterface $input) {
        $fileSystem = new Filesystem();
        $etreMigrationConfigFile = \Mage::getBaseDir('etc') . DS . "migrations.yml";
        $etreMigrationDBConfig = \Mage::getBaseDir('etc') . DS . "migrations-db.php";

        $hasNoConfiguration = is_null($input->getOption('configuration'));
        if ($hasNoConfiguration && $fileSystem->exists($etreMigrationConfigFile)) {
            $input->setOption('configuration', $etreMigrationConfigFile);
        }

        $hasNoDBConfiguration = is_null($input->getOption('db-configuration'));
        if ($hasNoDBConfiguration && $fileSystem->exists($etreMigrationDBConfig)) {
            $input->setOption('db-configuration', $etreMigrationDBConfig);
        }
    }
}