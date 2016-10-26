<?php

namespace Etre\Shell\Console\Commands\Setup;

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

class InitCommand extends Command
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
            ->setName('etre:setup:init')
            ->setDescription('Create public directory and symlink js, media, and skin directories.')
            ->setHelp("Create a Magento \"public\" director to assist in protecting application code.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->getApplication()->initMagento();
        $this->makePublicDirectory();
        $output->writeln("<info>Created ./public directory</info>");
        $this->symlinkPublicMageRoot(".htaccess");
        $output->writeln("<info>Linked .htaccess in public directory</info>");
        $this->createIndex();
        $output->writeln("<info>Created index.php in public directory</info>");
        $this->symlinkPublicMageRoot("js");
        $output->writeln("<info>Linked js in public directory</info>");
        $this->symlinkPublicMageRoot("skin");
        $output->writeln("<info>Linked skin in public directory</info>");
        $this->symlinkPublicMageRoot("media");
        $output->writeln("<info>Linked media in public directory</info>");
        $this->symlinkPublicMageRoot("errors");
        $output->writeln("<info>Linked errors in public directory</info>");
        $this->symlinkPublicMageRoot("favicon.ico");
        $output->writeln("<info>Linked favicon.ico in public directory</info>");
        $this->symlinkPublicMageRoot("sitemap");
        $output->writeln("<info>Linked sitemaps in public directory</info>");
        $this->symlinkPublicMageRoot("var");
        $output->writeln("<info>Linked var in public directory</info>");
        $output->writeln([
            "<comment>All done! You can set your document root to ./public.</comment>",
            "\t<info> - Please make sure your public directory and index.php are accessible by your server.</info>"
        ]);

    }

    /**
     * @return bool
     */
    protected function makePublicDirectory()
    {
        $directoryHelper = $this->directoryHelper;
        $magentoDirectory = $directoryHelper->getApplicationDirectory();
        $pathToPublic = $magentoDirectory . $directoryHelper::DS . "public";
        mageDelTree($pathToPublic);
        return mkdir($pathToPublic,2770);
    }

    protected function symlinkPublicMageRoot($imitationDirectoryName)
    {
        $directoryHelper = $this->directoryHelper;
        $magentoDirectory = $directoryHelper->getApplicationDirectory();
        $pathToPublic = $magentoDirectory . $directoryHelper::DS . "public";
        $link = $pathToPublic . $directoryHelper::DS . $imitationDirectoryName;
        $target = $magentoDirectory . $directoryHelper::DS . $imitationDirectoryName;

        return symlink($target, $link);
    }

    protected function createIndex()
    {
        $directoryHelper = $this->directoryHelper;
        $magentoDirectory = $directoryHelper->getApplicationDirectory();
        $pathToPublic = $magentoDirectory . $directoryHelper::DS . "public";
        $fileContents = "<?php".
            "\n\tdefine('MAGENTO_ROOT', dirname(getcwd()));".
            "\n\trequire_once '../index.php';";
        $indexFile = $pathToPublic . $directoryHelper::DS . "index.php";
        file_put_contents($indexFile,$fileContents);
    }

    /**
     * @param OutputInterface $output
     */
    protected function writeBlankLn(OutputInterface $output)
    {
        $output->writeln("");
    }
}