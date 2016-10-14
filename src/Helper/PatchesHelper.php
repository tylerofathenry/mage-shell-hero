<?php

namespace Etre\Shell\Helper;

use Etre\Shell\Helper\DirectoryHelper;

class PatchesHelper
{
    protected $STATUS_REVERTED = "REVERTED";
    protected $STATUS_APPLIED = "APPLIED";
    private $directoryHelper = "";

    /**
     * Patches constructor.
     */
    public function __construct()
    {
        $directoryHelper = new DirectoryHelper();
        $this->setDirectoryHelper($directoryHelper);
    }

    public function getPatchFileContents()
    {
        return file_get_contents($this->pathToPatchesList());
    }

    public function pathToPatchesList()
    {
        $directoryHelper = $this->getDirectoryHelper();
        $DS = $directoryHelper::DS;
        $applicationDirectory = $this->getDirectoryHelper()->getApplicationDirectory();
        $appliedPatchesFilePath = $applicationDirectory . $DS . 'app' . $DS . 'etc' . $DS . 'applied.patches.list';
        return $appliedPatchesFilePath;
    }

    /**
     * @return DirectoryHelper
     */
    public function getDirectoryHelper()
    {
        return $this->directoryHelper;
    }

    /**
     * @param DirectoryHelper $directoryHelper
     * @return PatchesHelper
     */
    public function setDirectoryHelper($directoryHelper)
    {
        $this->directoryHelper = $directoryHelper;
        return $this;
    }

    public function getData($sort = "DESC")
    {
        $parsedPatchContent = $this->getParsedPatchContent();
        $patches = [];
        array_walk($parsedPatchContent, function (&$object) use (&$patches) {
            global $appliedPatchId;
            $isHeaderObject = count($object) >= 7;
            if($isHeaderObject):
                $appliedPatchId = $object[4];
                $patches[$appliedPatchId]['headers'] = $object;
                $isReverted = count($object) == 8;
                if($isReverted):
                    $patches[$appliedPatchId]['status'] = $this->STATUS_REVERTED;
                else:
                    $patches[$appliedPatchId]['headers'][] = $this->STATUS_APPLIED;
                    $patches[$appliedPatchId]['status'] = $this->STATUS_APPLIED;
                endif;
            else:
                $patchDetail = $object[0];
                $patches[$appliedPatchId]['details'][] = $patchDetail;
            endif;
        });
        switch(strtoupper($sort)):
            case "DESC":
                return $patches = array_reverse($patches);
            default:
                //In oldest to newest order by default
                return $patches;
        endswitch;
    }

    private function getParsedPatchContent()
    {
        $csv = new \Varien_File_Csv();

        $patchListArray = $csv->setDelimiter("|")->getData($this->pathToPatchesList());
        array_walk_recursive($patchListArray, function (&$object) {
            if(is_string($object)):
                $object = trim($object);
            endif;
        });

        return $this->removeNullItems($patchListArray);
    }

    /**
     * @param $patchListArray
     * @return array
     */
    private function removeNullItems($patchListArray)
    {
        $patchListArray = array_map('array_filter', $patchListArray);
        $patchListArray = array_filter($patchListArray);
        return $patchListArray;
    }
}