<?php

namespace Etre\Shell\Console\Commands\Migrations;

use Doctrine\DBAL\Migrations\Tools\Console\Command\ExecuteCommand as DoctrineExecuteCommand;
use Etre\Shell\Helper\MigrationsHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExecuteCommand extends DoctrineExecuteCommand {

    protected $migrationsHelper;

    protected function configure() {

        parent::configure();
        $this->setName('etre:migrations:execute');
        $this->migrationsHelper = new MigrationsHelper();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getApplication()->initMagento();
        $this->migrationsHelper->initEtreConfig($input);

        parent::execute($input,$output);
    }


}