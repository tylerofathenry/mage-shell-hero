<?php

namespace Etre\Shell\Console\Commands\Patch;

use Etre\Shell\Helper\DirectoryHelper;
use Etre\Shell\Helper\PatchesHelper;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table as ConsoleTable;
use Symfony\Component\Console\Helper\TableCell as ConsoleTableCell;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ListCommand extends AbstractMagentoCommand
{
    protected $patchTableHeaders = [
        "Installed",
        "Name",
        "Applied Magento Version",
        "Version",
        "",
        "Release Date",
        "",
        "State",
    ];
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
            ->setName('etre:patch:list')
            ->setDescription('List applied patches')
            ->addArgument("id", null, "Optional, Numerical ID of patch: <comment>XXXX</comment> in SUPEE-<comment>XXXX</comment>")
            ->addArgument("version", null, "Optional, Patch version: <comment>Y</comment> in SUPEE-<comment>XXXX</comment> v<comment>Y</comment>")
            ->addOption('sort', null, InputOption::VALUE_OPTIONAL, "Sort by patch installation date: ASC | DESC", "DESC")
            ->addOption('minimal', "m", null, "List patches by name and version.")
            ->addOption('no-details', null, null, "Minimizes patch details shown")
            ->setHelp("This command lists your applied patches");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if(!$this->initMagento()) {
            return;
        }

        $lookupPatchId = $input->getArgument("id");
        $lookupPatchVersion = $input->getArgument("version");

        $this->abortInvalidPatchId($output, $lookupPatchId);
        $this->abortInvalidVersion($output, $lookupPatchVersion);

        $patchData = $this->patchesHelper->getPatches($input->getOption("sort"), $lookupPatchId, $lookupPatchVersion);
        $this->abortOnEmptyPatches($output, $patchData, $lookupPatchId, $lookupPatchVersion);

        if($input->getOption("minimal")):
            $this->outputResponseMinimal($output, $patchData);
        endif;

        $this->executeNonMinimalResponse($input, $output, $patchData);
        $output->writeln('');

    }

    /**
     * @param OutputInterface $output
     * @param $lookupPatchId
     * @param $patchesHelper
     */
    protected function abortInvalidPatchId(OutputInterface $output, $lookupPatchId)
    {
        if($lookupPatchId && !$this->patchesHelper->isValidPatchId($lookupPatchId)):
            $this->abortWithMessage("Invalid patch Id provided.", $output);
        endif;
    }

    /**
     * @param OutputInterface $output
     */
    protected function abortWithMessage($message, OutputInterface $output)
    {
        $output->writeln("<comment>{$message}</comment>");
        exit;
    }

    /**
     * @param OutputInterface $output
     * @param $lookupPatchVersion
     * @param $patchesHelper
     */
    protected function abortInvalidVersion(OutputInterface $output, $lookupPatchVersion)
    {
        if($lookupPatchVersion && $lookupPatchVersion && !$this->patchesHelper->isValidVersion($lookupPatchVersion)):
            $this->abortWithMessage("Invalid patch version provided.", $output);
        endif;
    }

    /**
     * @param OutputInterface $output
     * @param $patchData
     * @param $lookupPatchId
     * @param $lookupPatchVersion
     */
    protected function abortOnEmptyPatches(OutputInterface $output, $patchData, $lookupPatchId, $lookupPatchVersion)
    {
        if(empty($patchData)):
            if($lookupPatchId && $lookupPatchVersion):
                $this->abortWithMessage("<comment>No patches found matching ID SUPEE-{$lookupPatchId} v{$lookupPatchVersion}</comment>", $output);
            elseif($lookupPatchId):
                $this->abortWithMessage("<comment>No patches found matching ID SUPEE-{$lookupPatchId} at any version.</comment>", $output);
            else:
                $this->abortWithMessage("<comment>No patches appear in applied.patches.list or this file could not be parsed.</comment>", $output);
            endif;
        endif;
    }

    /**
     * @param OutputInterface $output
     * @param $patchData
     */
    protected function outputResponseMinimal(OutputInterface $output, $patchData)
    {
        foreach($patchData as $patch):
            $this->outputPatchTitle($output, $patch);
        endforeach;
        exit;
    }

    /**
     * @param OutputInterface $output
     * @param $patch
     */
    protected function outputPatchTitle(OutputInterface $output, $patch)
    {
        $patchTitle = $this->getPatchTitle($patch);
        $output->writeln("<info>{$patchTitle}</info>");
    }

    /**
     * @param $patch
     * @return string
     */
    protected function getPatchTitle($patch)
    {
        $patchName = $patch['headers'][1];
        $patchVersion = $patch['headers'][3];
        $patchState = "[{$patch['headers'][7]}]";
        $patchTitle = "{$patchName} {$patchVersion} {$patchState}";
        return $patchTitle;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $patchData
     * @param $progress
     */
    protected function executeNonMinimalResponse(InputInterface $input, OutputInterface $output, $patchData)
    {
        $progress = new ProgressBar($output, count($patchData));
        $output->writeln("");
        $progress->setMessage("% List Reviewed");
        $question = new Question("Press any key to proceed to next patch.");
        $helper = $this->getHelper('question');

        foreach($patchData as $patch):
            $this->outputPatchTitle($output, $patch);
            $this->outputPatchTable($input, $output, $patch);
            if(count($patchData) > 1):
                $progress->advance();
                $this->writeBlankLn($output);
                $helper->ask($input, $output, $question);
            endif;
            $output->writeln("");
        endforeach;

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param $patch
     */
    protected function outputPatchTable(InputInterface $input, OutputInterface $output, $patch)
    {
        $patchTable = new ConsoleTable($output);
        $this->setTableHeaders($patch, $patchTable);
        if(!$input->getOption('no-details')):
            foreach($patch['details'] as $patchDetail):
                $patchTable->addRow([new ConsoleTableCell($patchDetail, ['colspan' => count($patch['headers'])])]);
            endforeach;
        endif;
        $patchTable->setColumnWidths([null, null, null, 1])->render();
    }

    /**
     * @param $patch
     * @param $patchTable
     */
    protected function setTableHeaders($patch, ConsoleTable $patchTable)
    {
        $patchTable->setHeaders($this->patchTableHeaders);
        $patchTable->addRow($patch['headers']);
    }

    /**
     * @param OutputInterface $output
     */
    protected function writeBlankLn(OutputInterface $output)
    {
        $output->writeln("");
    }
}