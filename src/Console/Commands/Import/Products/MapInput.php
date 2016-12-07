<?php

namespace Etre\Shell\Console\Commands\Import\Products;

class MapInput
{
    const MAP_BREAK = ",";
    const MAP_DELIMITER = ":";
    protected $mappedAttributes = [];

    /**
     * Input constructor.
     * @param string $inputString
     */
    public function __construct($inputString)
    {
        $this->validateMappedInput($inputString);
        $this->setMappedAttributes($inputString);
    }

    /**
     * @param $mappedInputString
     */
    protected function validateMappedInput($mappedInputString)
    {
        $skuInMapString = strpos($mappedInputString, "sku:");
        if(!($skuInMapString >=0)):
            throw new \Exception("SKU mapping is required. It will be used to load the product.");
        endif;
        $MAP_DELIMITER = self::MAP_DELIMITER;
        if(is_string($mappedInputString)):
            $hasMapping = strpos($mappedInputString, $MAP_DELIMITER);
            if(!$hasMapping):
                throw new \Exception("Mapping must be joined by {$MAP_DELIMITER} . 'attribute_code:column_header' or 'attribute_code:column_indexcd -'");
            endif;
        else:
            $mappedInputType = gettype($mappedInputString);
            throw new \Exception("Expected mapped input to be a string. {$mappedInputType} provided.");
        endif;

        $mappingsToCheck = explode(self::MAP_BREAK, $mappedInputString);
        foreach($mappingsToCheck as $key => $mapping):
            $mappingsDelimited = explode($MAP_DELIMITER, $mapping);
            $delimitedMappingsFiltered = array_filter($mappingsDelimited);
            if(empty($delimitedMappingsFiltered)):
                $lastSuccessfulMap = $mappingsToCheck[$key - 1];
                if(isset($lastSuccessfulMap)):
                    throw new \Exception("The mapping after $lastSuccessfulMap appears to be improperly formatted");
                else:
                    throw new \Exception("Your first mapping appears to be improperly formatted.");
                endif;
            endif;
            $hasPartner = count($delimitedMappingsFiltered) !== 2;
            if($hasPartner):
                throw new \Exception("The item \"$mapping\" has no association in your mapping option. Use the format \"attribute_code:column_header\" or \"attribute_code:column_index\"");
            endif;
        endforeach;
    }

    public function getMappedAttributes()
    {
        return $this->mappedAttributes;
    }

    /**
     * @param string $mappedInputString
     * return array
     */
    protected function setMappedAttributes($mappedInputString)
    {
        $mappedInputBaseArray = explode(self::MAP_BREAK, $mappedInputString);
        $mappedInput = array_reduce($mappedInputBaseArray, function ($output, $item) {
            $mapping = explode(self::MAP_DELIMITER, $item);
            $attributeCode = $mapping[0];
            $fileMapping = $mapping[1];
            $output[$attributeCode] = $fileMapping;
            return $output;
        }, []);

        $this->mappedAttributes = $mappedInput;
        return $this;
    }
}