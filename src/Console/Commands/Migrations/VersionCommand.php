<?php

namespace Etre\Shell\Console\Commands\Migrations;

use Doctrine\DBAL\Migrations\Tools\Console\Command\VersionCommand as DoctrineVersionCommand;
use Etre\Shell\Helper\MigrationsHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VersionCommand extends DoctrineVersionCommand {

    protected $migrationsHelper;

    public function execute(InputInterface $input, OutputInterface $output) {
        $this->getApplication()->initMagento();
        $this->migrationsHelper->initEtreConfig($input);

        parent::execute($input,$output);
    }

    protected function configure() {
        parent::configure();
        $this->setName('etre:migrations:version');
        $this->migrationsHelper = new MigrationsHelper();
    }
}