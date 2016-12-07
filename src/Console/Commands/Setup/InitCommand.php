<?php

namespace Etre\Shell\Console\Commands\Setup;

use Etre\Shell\Helper\DirectoryHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class InitCommand extends Command {
    protected $fileSystem;
    /** @var DirectoryHelper $directoryHelper */
    protected $directoryHelper;

    /** @var string $directoryHelper public directory name */
    protected $publicDirName;


    /**
     * PatchCommand constructor.
     * @param $patchHelper
     */
    public function __construct($name = NULL) {
        $this->directoryHelper = new DirectoryHelper();
        $this->fileSystem = new Filesystem();
        parent::__construct($name);
    }

    public function configure() {
        $this
            ->setName('etre:setup:init')
            ->addArgument("site-path", InputArgument::OPTIONAL, 'Defaults to "public"', "public")
            ->setDescription('Create public directory and symlink js, media, and skin directories.')
            ->setHelp("Create a Magento \"public\" director to assist in protecting application code.");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->sanitizeSitePathArgument($input);
        $directoryHelper = $this->directoryHelper;
        $magentoDirectory = $directoryHelper->getApplicationDirectory();
        $this->publicDirName = $magentoDirectory . $directoryHelper::DS . $input->getArgument("site-path");
        if ($this->fileSystem->exists($this->publicDirName)):
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("<info>{$this->publicDirName} directory already exists. Do you want to continue delete this directory and continue?</info><comment>[Default: No]</comment> ", FALSE);
            if (!$helper->ask($input, $output, $question)) {
                return;
            }
            $directoryHelper->delTree($this->publicDirName);
        endif;
        $this->makePublicDirectory();
        $output->writeln("<info>Created ./public directory</info>");
        $this->createIndex();
        $output->writeln("<info>Creating .gitignore.</info>");
/*        $this->createGitIgnore();*/
        $this->cp(".htaccess", $output);
        $this->createLink("js", $input, $output);
        $this->createLink("media", $input, $output);
        $this->createLink("skin", $input, $output);
        $this->createLink("favicon.ico", $input, $output);
        $this->createLink("sitemap", $input, $output);
        $this->createLink("wp", $input, $output);
        if ($input->hasArgument("site-path")):
            $completeMessage = "<comment>All done! A Magento site can now be pointed to </comment>{$input->getArgument('site-path')}";
        else:
            $completeMessage = "<comment>All done! You can set your document root to </comment>{$this->publicDirName}";
        endif;
        $output->writeln([
            $completeMessage,
            "\t<info>1. Make sure your server can access </info>{$this->publicDirName}",
            "\t<info>2. This directory uses symbolic links. Magento's Apache rules enable this by default and should not cause a problem.</info>",
/*            "\t<info>3. A .gitignore file has been created.</info>",*/
            "\t\t<info>- The contents in this directory will not be added to a Git repository since the symbolic links paths are absolute.</info>",
            "\t\t<info>- This command will need to be executed again in production if the file paths are different.</info>",
        ]);

    }

    /**
     * @return bool
     */
    protected function makePublicDirectory() {
        return $this->fileSystem->mkdir($this->publicDirName);
    }

    protected function createIndex() {
        $directoryHelper = $this->directoryHelper;
        $magentoDirectory = $directoryHelper->getApplicationDirectory();
        $pathToPublic = $this->publicDirName;
        $fileContents = '<?php

    define(\'MAGENTO_ROOT\', \'' . $magentoDirectory . '\');
    $maintenanceFile = MAGENTO_ROOT . \'/maintenance.flag\';

    if (file_exists($maintenanceFile)) {
        include_once MAGENTO_ROOT . \'/errors/503.php\';
        exit;
    }

    require_once MAGENTO_ROOT.\'/index.php\';';
        $indexFile = $pathToPublic . $directoryHelper::DS . "index.php";
        file_put_contents($indexFile, $fileContents);
    }

    protected function createGitIgnore() {
        $directoryHelper = $this->directoryHelper;
        $pathToPublic = $this->publicDirName;
        $fileContents = '*';
        $indexFile = $pathToPublic . $directoryHelper::DS . ".gitignore";
        file_put_contents($indexFile, $fileContents);
    }

    /**
     * @param OutputInterface $output
     */
    protected function cp($magentoPath, OutputInterface $output) {
        $style = new OutputFormatterStyle('red', 'yellow', ['bold', 'blink']);
        $output->getFormatter()->setStyle('warning', $style);

        $directoryHelper = $this->directoryHelper;
        $magentoDirectory = $directoryHelper->getApplicationDirectory();
        $pathToPublic = $this->publicDirName;
        $destination = $pathToPublic . $directoryHelper::DS . $magentoPath;
        $source = $magentoDirectory . $directoryHelper::DS . $magentoPath;
        copy($source, $destination);
        $output->writeln([
            "<info>Copied</info> $magentoPath <info>to </info> $destination",
            "<warning>Notice:</warning> <comment>If the path to the new site is </comment>http://[magento-root]/newsite...",
            "\t<comment>1. Uncomment RewriteBase in</comment> $destination",
            "\t<comment>2. Set </comment>RewriteBase /newsite/",
        ]);
    }

    /**
     * @param OutputInterface $output
     */
    protected function createLink($magentoFileOrDirName, InputInterface $input, OutputInterface $output) {
        $relativePathToMagentoAsset = $this->getRelativePathToRoot($input) . $magentoFileOrDirName;
        $absolutePathToMagentoAsset = $this->directoryHelper->getApplicationDirectory() . DIRECTORY_SEPARATOR . $magentoFileOrDirName;
        $link = $magentoFileOrDirName;

        if ($this->fileSystem->exists($absolutePathToMagentoAsset)):
            chdir($this->publicDirName);
            $this->fileSystem->symlink($relativePathToMagentoAsset, $link);
            chdir($this->directoryHelper->getApplicationDirectory());
            $output->writeln("<info>Linked $magentoFileOrDirName in public directory</info>");
        else:
            $output->writeln('<error>Could not link to ' . $magentoFileOrDirName . '. The file or directory does not exist in your Magento root.</error>');
        endif;
    }

    /**
     * @param $magentoPath
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @return string
     */
    protected function getRelativePathToRoot(InputInterface $input) {
        $subDirLevels = array_filter(explode(DIRECTORY_SEPARATOR, $input->getArgument("site-path")));
        $relativePath = "";
        foreach ($subDirLevels as $level):
            $relativePath .= "../";
        endforeach;

        return $relativePath;
    }

    /**
     * @param OutputInterface $output
     */
    protected function writeBlankLn(OutputInterface $output) {
        $output->writeln("");
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @return mixed
     */
    protected function sanitizeSitePathArgument(InputInterface $input) {
        $sitePathesToArray = explode(DIRECTORY_SEPARATOR, $input->getArgument("site-path"));
        $filterEmptyPaths = array_filter($sitePathesToArray);
        $sanitizedString = implode(DIRECTORY_SEPARATOR, $filterEmptyPaths);
        return $input->setArgument('site-path', $sanitizedString);
    }
}