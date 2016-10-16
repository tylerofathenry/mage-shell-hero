<?php

namespace Etre\Shell\Helper;

use Etre\Shell\Helper\DirectoryHelper;
use N98\Util\Console\Helper\MagentoHelper;
use Symfony\Component\Debug\Debug;

class PatchesHelper extends MagentoHelper
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

    public function getPatches($sort = "DESC", $lookupPatchId = null, $lookupPatchVersion = null)
    {
        $searchByPatchId = $this->isValidPatchId($lookupPatchId) ? intval($lookupPatchId) : false;
        $hasVersionCriteria = $this->isValidVersion($lookupPatchVersion);
        $searchByVersion = $searchByPatchId && $hasVersionCriteria;
        $patchList = $this->getPatchList($sort);
        if($searchByPatchId):
            $patchList = $this->applyPatchIdFilter($lookupPatchId, $patchList);
        endif;
        if($searchByVersion):
            $patchList = $this->applyPatchVersionFilter($lookupPatchVersion, $patchList);
        endif;
        return $patchList;

    }

    /**
     * @param $lookupPatchId
     * @return bool
     */
    public function isValidPatchId($lookupPatchId)
    {
        if(is_numeric($lookupPatchId)):
            return intval($lookupPatchId) > 0;
        endif;
        return false;
    }

    /**
     * @param $lookupPatchVersion
     * @return bool
     */
    public function isValidVersion($lookupPatchVersion)
    {
        if(is_numeric($lookupPatchVersion)):
            return intval($lookupPatchVersion) > 0;
        endif;
    }

    public function getPatchList($sort = "DESC")
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
        $mage = \Mage::app();
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

    /**
     * @param $lookupPatchId
     * @param $searchByPatchId
     * @param $patchList
     */
    protected function applyPatchIdFilter($lookupPatchId, $patchList)
    {
        foreach($patchList as $patchKey => $patch):
            $patchName = strtoupper($patch['headers'][1]);
            $patchNameParts = explode("-", $patchName);
            $currentPatchId = $patchNameParts[1];
            if($currentPatchId !== $lookupPatchId):
                unset($patchList[$patchKey]);
            endif;
        endforeach;
        return $patchList;
    }

    /**
     * @param $lookupPatchVersion
     * @param $searchByPatchId
     * @param $patchList
     */
    protected function applyPatchVersionFilter($lookupPatchVersion, $patchList)
    {
        foreach($patchList as $patchKey => $patch):
            $patchVersion = strtoupper($patch['headers'][3]);
            $patchVersionParts = explode("V", $patchVersion);
            $currentPatchVersion = $patchVersionParts[1];
            if($currentPatchVersion !== $lookupPatchVersion):
                unset($patchList[$patchKey]);
            endif;
        endforeach;
        return $patchList;
    }
}