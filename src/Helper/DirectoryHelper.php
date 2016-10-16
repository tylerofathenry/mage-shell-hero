<?php

namespace Etre\Shell\Helper;

class DirectoryHelper
{
    const DS = DIRECTORY_SEPARATOR;

    /**
     * @param $currentDirectory
     * @param $parentDirectoryPosition
     */
    function getApplicationDirectory()
    {
        $currentDirectory = $this->getCurrentDirectory();
        $directoryToFind = 'lib';
        $shellSearch = $this->getDirectoryLiteralTerm($directoryToFind);
        $parentDirectoryPosition = $this->getParentDirectoryPosition($currentDirectory, $shellSearch);
        return substr($currentDirectory, 0, $parentDirectoryPosition);
    }

    /**
     * @return string
     */
    function getCurrentDirectory()
    {
        return __DIR__;
    }

    /**
     * @param $directoryName
     * @return string
     */
    function getDirectoryLiteralTerm($directoryName)
    {
        $literalDirectorySearchTerm = self::DS . $directoryName . self::DS;
        return $literalDirectorySearchTerm;
    }

    /**
     * @param $currentDirectory
     * @param $shellSearch
     * @return string
     */
    function getParentDirectoryPosition($currentDirectory, $shellSearch)
    {
        $parentDirectoryPosition = strval(strrpos($currentDirectory, $shellSearch, 0));
        return $parentDirectoryPosition;
    }
}