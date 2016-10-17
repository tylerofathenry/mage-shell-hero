<?php

namespace Etre\Shell\Console\Commands\Import;

use Etre\Shell\Console\Commands\Import\Products\MapInput;
use Etre\Shell\Helper\DirectoryHelper;
use Etre\Shell\Helper\PatchesHelper;
use Maatwebsite\Excel\Classes\PHPExcel;
use Maatwebsite\Excel\Excel;
use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ProductsCommand extends AbstractMagentoCommand
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
            ->setName('etre:import:products')
            ->setDescription('Import products')
            ->addArgument("path", null, "Path to file in var directory: <comment>import/filename.csv</comment>")
            ->addOption("map-attribute", "m", InputOption::VALUE_REQUIRED,
                "Map attribute codes to headers." .
                "\n<comment>-m mage_attribute_code:file_column_header,mage_attribute_code:file_column_header,...</comment>" .
                "\n<comment>-m sku:column_header_sku,description:column_header_description,...</comment>" .
                "\n<comment>-m sku:column_index,description:column_index,...</comment>" .
                "\n<comment>-m sku:1,description:3,short_description:2,...</comment>"
            )
            ->setHelp("This command lists your applied patches");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->detectMagento($output);
        if(!$this->initMagento()) {
            return;
        }
        $helper = $this->getHelper('question');

        $attributeMapString = $input->getOption("map-attribute");
        $mapInput = new MapInput($attributeMapString);
        $mapInput->getMappedAttributes();

        $importFile = \Mage::getBaseDir() . DS . "var" . DS . "import" . DS . "import-20161017070019-1_Prepped_for_import_-_artificial_attributes-libre.csv";
        $excelDocument = $this->loadFile($importFile);
        $sheet = $excelDocument->getSheet(0);
        $lastColumn = $sheet->getHighestColumn();
        $lastColumn++;
        $row = 1;
        $inputMappings = $mapInput->getMappedAttributes();
        $mappings = $this->mapFileColumns($lastColumn, $sheet, $row, $inputMappings);
        $numberOfRows = $sheet->getHighestRow();
        $numberOfColumns = $sheet->getHighestColumn();

        $headerCount = 1;
        $totalItemsProgress = new ProgressBar($output, $numberOfRows - $headerCount);
        $output->writeln("");
        $totalItemsProgress->setMessage("% Items Processed");

        $fileDataRows = $sheet->toArray(null, null, true, true);
        $row = 1;
        foreach($fileDataRows as $sheetRow):
            if($row == 1):
                $row++;
                continue;
            endif;
            $totalItemsProgress->advance();
            $output->writeln("");
            foreach($sheetRow as $columnLetter => $cellValue):
                $rowMap[$mappings[$columnLetter]['magento_attribute_map']] = $cellValue;
            endforeach;
            /** @var \Mage_Catalog_Model_Product $product */
            $PRODUCT_IDENTIFIER = self::PRODUCT_IDENTIFIER;
            $rowSku = $rowMap[$PRODUCT_IDENTIFIER];
            PRODUCT_LOAD:
            $product = \Mage::getModel('catalog/product')->loadByAttribute($PRODUCT_IDENTIFIER, $rowSku);
            if(!$product):
                $output->writeln("<info>Could not load product by {$PRODUCT_IDENTIFIER}:{$rowSku}</info>");
                $promptForSku = new Question("You can correct the SKU by typing it in or skip by pressing enter: ");
                $questionResponse = $helper->ask($input, $output, $promptForSku);
                if(!$questionResponse) {
                    //Skip product
                    continue;
                } else {
                    $rowSku = $questionResponse;
                        goto PRODUCT_LOAD;
                }
            endif;
            $attributesToUpdate = $this->itemsToUpdateFilter($rowMap);
            foreach($attributesToUpdate as $attributeCode => $values):
                /** @var \Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
                $attribute = $product->getResource()->getAttribute($attributeCode);
                switch($attribute->getFrontendInput()):
                    case "multiselect":
                        $attributeAvailableOptions = $attribute->getSource()->getAllOptions();
                        $importedValues = explode(",", $values);
                        foreach($importedValues as $importedValue):
                            $importedValue = trim($importedValue);
                            foreach($attributeAvailableOptions as $availableOption):
                                $existingLabel = trim($availableOption['label']);
                                $exitingId = $availableOption['value'];
                                if($existingLabel == $importedValue):
                                    $idsToAssign[] = $exitingId;
                                endif;
                            endforeach;
                        endforeach;
                        $idsToAssignString = implode(",", $idsToAssign);
                        $product->setData($attributeCode, $idsToAssignString)->getResource()->saveAttribute($product, $attributeCode);
                        break;
                    case "boolean":
                        $value = strtolower($values);
                        switch($value):
                            case "yes":
                                $value = 1;
                                break;
                            case "no":
                                $value = 0;
                                break;
                            default:
                                $value = boolval($value);
                                $output->writeln([
                                    "<info>Importing {$rowSku}: {$attributeCode} was not \"Yes\"/\"No\"</info>",
                                    "<info>Using boolean conversion to save this row. New Value: {$value}</info>",
                                ]);
                                break;
                        endswitch;
                        $product->setData($attributeCode, $value)->getResource()->saveAttribute($product, $attributeCode);
                        break;
                    case "text":
                        $product->setData($attributeCode, $values)->getResource()->saveAttribute($product, $attributeCode);
                        break;
                    default:
                        $product->setData($attributeCode, $values)->getResource()->saveAttribute($product, $attributeCode);
                        break;
                endswitch;
            endforeach;
        endforeach;
        $totalItemsProgress->finish();
        /*for($row = 1; $row <= $numberOfRows; $row++) {
            if($row == 1) continue;
            $output->writeln("");
            $totalItemsProgress->setMessage("% product progress.");
            for($column = 'A'; $column != $lastColumn; $column++):
                $cell = $sheet->getCell($column . $row);
                $columnLetter = $cell->getColumn();
                $colIndex = \PHPExcel_Cell::columnIndexFromString($columnLetter);
                $columnHasMapping = isset($mappings[$colIndex]);
                if(!$columnHasMapping) continue;

            endfor;
            $currentSheetRow = $sheet->rangeToArray('A' . $row . ':' . $numberOfColumns . $row, null, true, false);
        }*/
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