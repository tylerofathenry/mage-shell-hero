<?php

namespace Etre\Shell\Console\Commands\Installer;

use Etre\Shell\Exceptions\ExtensionMissingException;
use Etre\Shell\Exceptions\InvalidUrlException;
use Etre\Shell\Helper\DirectoryHelper;
use Etre\Shell\Helper\PatchesHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RecoverCommand extends Command
{
    protected $zendHttpConfig;
    /** @var DirectoryHelper $directoryHelper */
    protected $directoryHelper;

    /** @var PatchesHelper $patchesHelper */
    protected $patchesHelper;
    protected $edition;
    protected $version;

    /**
     * PatchCommand constructor.
     * @param $patchHelper
     */
    public function __construct($name = null)
    {
        $this->zendHttpConfig = [
            'adapter'      => 'Zend_Http_Client_Adapter_Socket',
            'ssltransport' => 'tls',
        ];
        $this->directoryHelper = new DirectoryHelper();
        $this->patchesHelper = new PatchesHelper($this->directoryHelper);

        parent::__construct($name);
    }

    public function configure()
    {
        $this
            ->setName('installer:recover')
            ->setDescription("Missing your install.php? Download it quick and easy.")
            ->addOption("mage-edition", "E", InputOption::VALUE_OPTIONAL, "Override the Magento installer edition: <comment>Enterprise|Community</comment>")
            ->addOption("mage-version", "X", InputOption::VALUE_OPTIONAL, "Override the Magento installer version: <comment>1.9.3.0|1.9.2.4|1.9.2.3|...</comment>")
            ->setHelp("This command will download the install.php for your version of Magento or another version if specified.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->writeBlankLn($output);
        $options['is_installed'] = false;
        $output->writeln("<info>Initiating installer</info>");
        try {
            \Mage::app();
            $this->initEdition($input);
            if(!$this->checkIsValidEdition($output)) return;

            $this->initVersion($input);
            if(!$this->checkIsValidVersion($output)) return;

            if(!extension_loaded('curl')) {
                throw new ExtensionMissingException("curl extension required.");
            }
            $output->writeln("Getting Magento installer for your version: <info>{$this->getVersion()}</info>");
            // Set the configuration parameters
            $installerHttpLookupClient = new \Zend_Http_Client("https://api.github.com/repos/OpenMage/magento-mirror/contents/install.php", $this->zendHttpConfig);
            $installerHttpLookupClient->setParameterGet("ref", $this->getVersion());
            $installerHttpLookupClient->request();
            $statusCode = $installerHttpLookupClient->getLastResponse()->getStatus();
            if($statusCode !== 200):
                $statusString = $installerHttpLookupClient->getLastResponse();
                throw new \Exception("Attempting to download Magento installer: could not reach GitHub API");
            endif;
            $mageVersionInstaller = json_decode($installerHttpLookupClient->getLastResponse()->getBody());

            $installerDownloader = new \Zend_Http_Client($mageVersionInstaller->download_url, $this->zendHttpConfig);
            $installerDownloader->request();
            $gitDownloadResponse = $installerDownloader->getLastResponse();
            $statusCode = $gitDownloadResponse->getStatus();
            if($statusCode !== 200):
                $statusString = $gitDownloadResponse->getHeaders()['Status'];
                throw new InvalidUrlException("The attempt to download {$mageVersionInstaller->download_url} returned {$statusString}");
            endif;
            $directoryHelper = $this->directoryHelper;
            $installerSavePath = \Mage::getBaseDir() . $directoryHelper::DS . "install.php";
            file_put_contents($installerSavePath,$gitDownloadResponse->getBody());
        } catch(\Zend_Db_Adapter_Exception $e) {
            $this->outputMissingExtensionMessage($output, $e);

        } catch(ExtensionMissingException $e) {
            $this->outputMissingExtensionMessage($output, $e);
        } catch(InvalidUrlException $e) {
            $output->writeln([
                "<info>Could not complete request:</info>",
                "\tError:\t<error>{$e->getMessage()}</error>"
            ]);
        }catch(\Exception $e){
            $output->writeln([
                "\tError:\t<error>{$e->getMessage()}</error>"
            ]);
        }
        $output->writeln("<info>Installer has been recovered and placed in your Magento root directory</info>");

        $this->writeBlankLn($output);
    }

    /**
     * @param OutputInterface $output
     */
    protected function writeBlankLn(OutputInterface $output)
    {
        $output->writeln("");
    }

    /**
     * @param InputInterface $input
     */
    protected function initEdition(InputInterface $input)
    {
        if($input->getOption("mage-edition")):
            $this->setEdition($input->getOption("mage-edition"));
        else:
            $this->setEdition(\Mage::getEdition());
        endif;
    }

    /**
     * @param OutputInterface $output
     */
    protected function checkIsValidEdition($output)
    {
        $edition = strtolower($this->getEdition());

        if($edition !== "community"):
            $output->writeln("<comment>{$edition} is not supported by this installer.</comment>");

            return false;
        endif;
        return true;
    }

    /**
     * @return mixed
     */
    public function getEdition()
    {
        return $this->edition;
    }

    /**
     * @param mixed $edition
     */
    public function setEdition($edition)
    {
        $this->edition = $edition;
    }

    /**
     * @param InputInterface $input
     */
    protected function initVersion(InputInterface $input)
    {
        if($input->getOption("mage-version")):
            $this->setVersion($input->getOption("mage-version"));
        else:
            $this->setVersion(\Mage::getVersion());
        endif;
    }

    /**
     * @param OutputInterface $output
     */
    protected function checkIsValidVersion($output)
    {
        $version = $this->getVersion();
        $zendClient = new \Zend_Http_Client("https://api.github.com/repos/OpenMage/magento-mirror/tags", $this->zendHttpConfig);
        $zendClient->request();
        $gitResponse = $zendClient->getLastResponse();
        $statusCode = $gitResponse->getStatus();
        if($statusCode !== 200) throw new InvalidUrlException("Could not communicate with GitHub:\n\thttps://api.github.com/repos/OpenMage/magento-mirror/tags\n\t{$gitResponse->getBody()}");
        $gitVersionResults = json_decode($gitResponse->getBody());

        if(!$this->objArraySearch($gitVersionResults, "name", $version)):
            $availableVersions = [];
            foreach($gitVersionResults as $availableVersion):
                $availableVersions[] = $availableVersion->name;
            endforeach;
            $availableVersions = implode(" | ", $availableVersions);
            $output->writeln([
                "<info>Version Response:</info>",
                "\t<comment>{$version} is not supported by this installer.</comment>",
                "\t<comment>Available Versions:</comment> {$availableVersions}",
            ]);
            return false;
        endif;
        return true;
    }

    /**
     * @return mixed
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param mixed $version
     * @return SetupCommand
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @param OutputInterface $output
     * @param $e
     */
    protected function outputMissingExtensionMessage(OutputInterface $output, $e)
    {
        $missingExtension = $this->getMissingExtension($e);
        $missingExtensionUrl = $this->getExtensionUrl($missingExtension);
        $output->writeln([
            "PHP Plugin missing:",
            "\tError:\t<error>{$e->getMessage()}</error>",
            "\tHelper:\tTry executing <info>sudo [apt|apt-get|yum] install {$missingExtension}</info> or visit: {$missingExtensionUrl} for installation instructions.",
            "\t\t<info>Sudo may be needed</info>",
        ]);
    }

    /**
     * @param $e
     * @return mixed
     */
    protected function getMissingExtension($e)
    {
        $errorMessage = $e->getMessage();
        $extension = explode("extension", $errorMessage)[0];
        $extensionTidied = trim($extension);
        return "php-" . str_replace('_', '-', $extensionTidied);
    }

    /**
     * @param $missingExtension
     * @return string
     */
    protected function getExtensionUrl($missingExtension)
    {
        $urlParam = str_replace("php-", "", $missingExtension);
        return "http://php.net/manual/en/ref.{$urlParam}.php";
    }

    public static function objArraySearch($array, $index, $value)
    {
        foreach($array as $arrayInf) {
            if($arrayInf->{$index} == $value) {
                return $arrayInf;
            }
        }
        return null;
    }


}