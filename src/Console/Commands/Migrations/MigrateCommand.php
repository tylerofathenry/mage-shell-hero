<?php

namespace Etre\Shell\Console\Commands\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Migrations\Tools\Console\Command\MigrateCommand as DoctrineMigrateCommand;
use Etre\Shell\Helper\MigrationsHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class MigrateCommand extends DoctrineMigrateCommand {

    protected $migrationsHelper;

    public function execute(InputInterface $input, OutputInterface $output) {
        $this->getApplication()->initMagento();
        $this->migrationsHelper->initEtreConfig($input);

        parent::execute($input,$output);
    }

    protected function configure() {
        parent::configure();
        $this->setName('etre:migrations:migrate');
        $this->migrationsHelper = new MigrationsHelper();
    }
}