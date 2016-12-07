<?php

namespace Etre\Shell\Console\Commands\Import;

use Etre\Shell\Console\Commands\Import\Products\MapInput;
use Etre\Shell\Helper\DirectoryHelper;
use Etre\Shell\Helper\PatchesHelper;
use Maatwebsite\Excel\Classes\PHPExcel;
use Maatwebsite\Excel\Excel;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ListCommand extends AbstractMagentoCommand
{
    const PRODUCT_IDENTIFIER = "sku";
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
            ->setName('etre:import:list')
            ->setDescription('List files in import directory.')
            ->addArgument("path",InputArgument::OPTIONAL, "Relative path to subdirectory within ./var/import")
            ->setHelp("This command lists available files");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if(!$this->initMagento()) {
            return;
        }
        $helper = $this->getHelper('question');
        $importDirectory = \Mage::getBaseDir() . DS . "var" . DS . "import" . DS . $input->getArgument("path");
        $output->writeln(scandir($importDirectory));
    }

    /**
     * @param string $importFile
     * @return \PHPExcel
     */
    protected function loadFile($importFile)
    {
        try {
            $inputFileType = \PHPExcel_IOFactory::identify($importFile);
            $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($importFile);
            return $objPHPExcel;
        } catch(Exception $e) {
            die('Error loading file "' . pathinfo($importFile, PATHINFO_BASENAME) . '": ' . $e->getMessage());
        }
        return $objPHPExcel;
    }

    /**
     * @param $lastColumn
     * @param $sheet
     * @param $row
     * @param $inputMappings
     */
    protected function mapFileColumns($lastColumn, $sheet, $row, $inputMappings)
    {
        $columnReverseMapping = [];

        for($column = 'A'; $column != $lastColumn; $column++):
            $cell = $sheet->getCell($column . $row);
            $columnLetter = $cell->getColumn();
            $colIndex = \PHPExcel_Cell::columnIndexFromString($columnLetter);
            unset($mapped_magento_attribute);

            $columnHeaderValue = $cell->getValue();
            foreach($inputMappings as $attribute_code => $headerValue):
                if(($headerValue == $columnHeaderValue || ($headerValue == $colIndex))):
                    $mapped_magento_attribute = $attribute_code;
                endif;
            endforeach;
            if(!isset($mapped_magento_attribute)):
                continue;
            else:
                $columnReverseMapping[$columnLetter]['magento_attribute_map'] = $mapped_magento_attribute;
                $columnReverseMapping[$columnLetter]["columnIndex"] = $colIndex;
                $columnReverseMapping[$columnLetter]["columnName"] = $columnHeaderValue;
                $columnReverseMapping[$columnLetter]["column"] = $columnLetter;
            endif;
        endfor;
        return $columnReverseMapping;
    }

    /**
     * @param $rowMap
     * @return mixed
     */
    protected function itemsToUpdateFilter($rowMap)
    {
        $attributesToUpdate = $rowMap;
        unset($attributesToUpdate[self::PRODUCT_IDENTIFIER]);
        return $attributesToUpdate;
    }

}