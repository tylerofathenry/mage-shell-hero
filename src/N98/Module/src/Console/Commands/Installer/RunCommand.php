<?php

namespace Etre\Shell\Console\Commands\Installer;

use Etre\Shell\Exceptions\InvalidUrlException;
use Etre\Shell\Helper\DirectoryHelper;
use Etre\Shell\Helper\PatchesHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    const adminFrontNameDefault = "{random}";
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
            ->setName('installer:run')
            ->setDescription("Execute the Magento Installer");
        foreach($this->getMagentoNativeOptions() as $argName => $optionConfig):
            try {
                $argMode = $optionConfig['required'] ? InputArgument::REQUIRED : null;
                $argDefault = $optionConfig['default'] ? $optionConfig['default'] : false;
                $argDescription = $optionConfig['comment'];
                $this->addArgument($argName, $argMode, $argDescription);
            } catch(\Exception $e) {
                \Zend_Debug::dump($e->getMessage());
                \Zend_Debug::dump($argName);
                \Zend_Debug::dump($argMode);
                \Zend_Debug::dump($argDescription);
                \Zend_Debug::dump($argDefault);
                die;
            }
        endforeach;
        $this->addArgument("native", null, "<info>(Inactive)</info> Use Magento's native installer instead.");
        $this->setHelp("This command will download the install.php for your version of Magento or another version if specified.");
    }

    protected function getMagentoNativeOptions()
    {
        return [
            'license_agreement_accepted' => ['required' => true, 'comment' => 'Required, it will accept \'yes\' value only', 'default' => "no"],
            'locale'                     => ['required' => true, 'comment' => 'Required, Locale: <comment>en_US</comment>', 'default' => null],
            'timezone'                   => ['required' => true, 'comment' => 'Required, Time Zone: <comment>"America/Los_Angeles"</comment>', 'default' => null],
            'default_currency'           => ['required' => true, 'comment' => 'Required, Default Currency: <comment>USD</comment>', 'default' => null],
            'db_host'                    => ['required' => true, 'comment' => "You can specify server port: <comment>localhost:3307</comment>. If you are not using default UNIX socket, you can specify it here instead of host, ex.: <comment>/var/run/mysqld/mysqld.sock</comment>", 'default' => null],
            'db_name'                    => ['required' => true, 'comment' => 'Database Name', 'default' => null],
            'db_user'                    => ['required' => true, 'comment' => 'required, Database User Name', 'default' => null],
            'db_pass'                    => ['required' => true, 'comment' => 'required, Database User Password', 'default' => null],
            'url'                        => ['required' => true, 'comment' => 'required, URL the store is supposed to be available at', 'default' => null],
            'use_rewrites'               => ['required' => true, 'comment' => 'Use Web Server (Apache) Rewrites. <comment>Depends on Apache mod_rewrite</comment>', 'default' => "no"],
            'use_secure'                 => ['required' => true, 'comment' => 'Use Secure URLs (SSL)', 'default' => null],
            'secure_base_url'            => ['required' => true, 'comment' => 'Secure Base URL', 'default' => null],
            'use_secure_admin'           => ['required' => true, 'comment' => 'Run admin interface with SSL', 'default' => null],
            'admin_lastname'             => ['required' => true, 'comment' => 'Enables Charts on the backend\'s dashboard', 'default' => null],
            'admin_firstname'            => ['required' => true, 'comment' => 'required, admin user last name', 'default' => null],
            'admin_email'                => ['required' => true, 'comment' => 'required, admin user first name', 'default' => null],
            'admin_username'             => ['required' => true, 'comment' => 'required, admin username', 'default' => null],
            'admin_password'             => ['required' => true, 'comment' => 'required, admin user password', 'default' => null],
            'db_model'                   => ['required' => false, 'comment' => 'Database type', 'default' => "mysql4"],
            'db_prefix'                  => ['required' => false, 'comment' => 'Database Tables Prefix', 'default' => null],
            'skip_url_validation'        => ['required' => false, 'comment' => 'skip validating base url during installation or not. No by default', 'default' => "no"],
            'encryption_key'             => ['required' => false, 'comment' => 'Will be automatically generated and displayed on success, if not specified', 'default' => null],
            'session_save'               => ['required' => false, 'comment' => 'Where to store session data - in db or files. files by default', 'default' => "files", 'default' => null],
            'admin_frontname'            => ['required' => false, 'comment' => 'Admin panel path, random by default', 'default' => $this::adminFrontNameDefault],
            'enable_charts'              => ['required' => false, 'comment' => '', 'default' => null],
        ];
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if($this->isAdminFrontNameDefault($input)):
            $input->setArgument("admin_frontname", $this->randomKey(5));
        endif;
        \Zend_Debug::dump($input->getArguments());
        try {
            $output->writeln([
                "<comment>Starting Install</comment>",
                "<info>If there is a fatal error, make sure the installer can write to all files and folders.</info>"
            ]);
            /** @var \Mage_Install_Model_Installer_Console $installer */
            $installer = \Mage::getSingleton('install/installer_console');
            $app = \Mage::app();
            $app->setCurrentStore("default");
            if($installer->init($app)          // initialize installer
                && $installer->setArgs()        // set and validate script arguments
                && $installer->install()
            )       // do install
            {
                $output->writeln('SUCCESS: ' . $installer->getEncryptionKey());
                $output->writeln('Admin Path: ' . $input->getArgument("admin_frontname"));
                exit;
            }

        } catch(\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
        $output->writeln("It ran. but why?");

    }

    /**
     * @param InputInterface $input
     * @return bool
     */
    protected function isAdminFrontNameDefault(InputInterface $input)
    {
        return $input->getArgument("admin_frontname") == $this::adminFrontNameDefault;
    }

    private function randomKey($length)
    {
        $pool = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));
        $key = "";
        for($i = 0; $i < $length; $i++) {
            $key .= $pool[mt_rand(0, count($pool) - 1)];
        }
        return $key;
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

    public static function objArraySearch($array, $index, $value)
    {
        foreach($array as $arrayInf) {
            if($arrayInf->{$index} == $value) {
                return $arrayInf;
            }
        }
        return null;
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


}