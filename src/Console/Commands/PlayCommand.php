<?php

namespace Etre\Shell\Console\Commands;

use Etre\Shell\Helper\DirectoryHelper;
use Etre\Shell\Helper\PatchesHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class PlayCommand extends Command
{
    /** @var DirectoryHelper $directoryHelper */
    protected $directoryHelper;

    /** @var PatchesHelper $patchesHelper */
    protected $patchesHelper;

    /**
     * PatchCommand constructor.
     * @param $patchHelper
     */
    public function __construct($name = null)
    {
        $this->directoryHelper = new DirectoryHelper();
        $this->patchesHelper = new PatchesHelper($this->directoryHelper);

        parent::__construct($name);
    }

    public function configure()
    {
        $this
            ->setName('play:mage')
            ->setDescription('Example use of Mage application rendered within console.')
            ->setHelp("This command lists your applied patches");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->writeBlankLn($output);
        $output->writeln('First we\'re going to initiate Magento with <comment>\Mage::app();</comment>');
        \Mage::app();
        $output->writeln('Now  we can use $mage just lik we can Mage. Let\'s see what <comment>get_class(\Mage::getModel("catalog/product"))</comment> get us...');
        /** @var \Mage_Catalog_Model_Product $products */
        $products = \Mage::getModel("catalog/product");
        $class = get_class($products);
        $output->writeln("Result: <info>{$class}</info>");

        $this->writeBlankLn($output);
    }

    /**
     * @param OutputInterface $output
     */
    protected function writeBlankLn(OutputInterface $output)
    {
        $output->writeln("");
    }


}