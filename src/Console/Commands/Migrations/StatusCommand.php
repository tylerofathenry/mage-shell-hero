<?php

namespace Etre\Shell\Console\Commands\Migrations;

use Doctrine\DBAL\Migrations\Tools\Console\Command\StatusCommand as DoctrineStatusCommand;
use Etre\Shell\Helper\MigrationsHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends DoctrineStatusCommand {

    protected $migrationsHelper;

    public function execute(InputInterface $input, OutputInterface $output) {
        $this->getApplication()->initMagento();
        $this->migrationsHelper->initEtreConfig($input);

        parent::execute($input,$output);
    }

    protected function configure() {
        parent::configure();
        $this->setName('etre:migrations:status');
        $this->migrationsHelper = new MigrationsHelper();
    }
}