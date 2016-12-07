<?php

use Etre\Shell\Helper\DirectoryHelper;

$dirHelper = new DirectoryHelper();
$applicationDirectory = $dirHelper->getApplicationDirectory();
$DS = $dirHelper::DS;

$mageComposerAutoloader = $applicationDirectory . $DS . "app" . $DS . 'Mage.php';

/** @var Composer\Autoload\ClassLoader $loader */
$loader = require $mageComposerAutoloader;
