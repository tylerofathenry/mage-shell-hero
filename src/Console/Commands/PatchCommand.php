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

class PatchCommand extends Command
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
            ->setName('patch:list')
            ->setDescription('List applied patches')
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, "Sort by patch installation date: ASC | DESC","DESC")
            ->addOption('no-details', null, null, "Minimizes patch details shown")
            ->setHelp("This command lists your applied patches");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $patchesHelper = $this->patchesHelper;
        $patchData = $patchesHelper->getData($input->getOption("sort"));
// create a new progress bar (50 units)
        $progress['patchList'] = new ProgressBar($output, count($patchData));

// start and displays the progress bar
        $progress['patchList']->start();
        $progress['patchList']->setMessage("% List Reviewed");
        $question = new Question("Press any key to proceed to next patch.");
        $helper = $this->getHelper('question');

        foreach($patchData as $patch):
            $this->writeBlankLn($output);
            $this->writeBlankLn($output);
            $output->writeln("<info>{$patch['headers'][1]}</info>");
            $patchTable = new Table($output);
            $patchTable->setHeaders($patch['headers']);
            if(!$input->getOption('no-details')):
                foreach($patch['details'] as $patchDetail):
                    $patchTable->addRow([new TableCell($patchDetail, ['colspan' => count($patch['headers'])])]);
                endforeach;
            endif;
            $patchTable->setColumnWidths([null, null, null, 1])->render();
            $progress['patchList']->advance();
            $output->writeln("");
            $helper->ask($input, $output, $question);
        endforeach;

        $progress['patchList']->finish();
        /*
        $output->writeln([
            'Patches List',
            '============',
            "File: {$patchesHelper->pathToPatchesList()}",
            //"Applied Patches: {$patchesHelper->getPatchFileContents()}",
        ]);*/

        // End with new line
        $output->writeln('');

    }

    /**
     * @param OutputInterface $output
     */
    protected function writeBlankLn(OutputInterface $output)
    {
        $output->writeln("");
    }
}