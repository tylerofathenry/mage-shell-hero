<?php

namespace Etre\Shell\Console\Commands\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\DBAL\Migrations\Tools\Console\Command\GenerateCommand as DoctrineGenerateCommand;
use Etre\Shell\Helper\MigrationsHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;

class GenerateCommand extends DoctrineGenerateCommand {

    protected $migrationsHelper;

    protected function configure() {

        parent::configure();
        $this->setName('etre:migrations:generate');
        $this->migrationsHelper = new MigrationsHelper();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getApplication()->initMagento();
        $this->migrationsHelper->initEtreConfig($input);

        parent::execute($input,$output);
    }


}