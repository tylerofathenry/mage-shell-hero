<?php

namespace Etre\Shell\Console\Commands\Migrations\Settings;

use N98\Magento\Command\AbstractMagentoCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class InitCommand extends AbstractMagentoCommand {

    private static $_migration_table_name = "etre_doctrine_migrations";
    private static $_migrations_namespace = "DoctrineMigrations";

    private static $_ymlTemplate =
        '
name: Magento Migrations
migrations_namespace: <migrations_namespace>
table_name: <migrations_table_name_with_magento_prefix>
migrations_directory: <path_to_migrations>
';

    public function execute(InputInterface $input, OutputInterface $output) {
        $this->getApplication()->initMagento();
        $fileSystem = new Filesystem();
        $migrationsSaveLocation = \Mage::getBaseDir("app") . DS . "database" . DS . $this->getMigrationsNamespace();
        try {
            $fileSystem->mkdir($migrationsSaveLocation);
        } catch(IOException $e) {
            $output->writeln('<error>Could not create' . $migrationsSaveLocation . '</error>');
            exit;
        }
        $this->generateMigrationConfig($input, $output);
        $output->writeln("<info>Success!</info>");
    }

    /**
     * @return string
     */
    public function getMigrationsNamespace() {
        return self::$_migrations_namespace;
    }

    protected function generateMigrationConfig(InputInterface $input, OutputInterface $output) {
        $placeHolders = [
            '<migrations_namespace>',
            '<migrations_table_name_with_magento_prefix>',
            '<path_to_migrations>',
        ];

        $migrationsConfigSaveLocation = \Mage::getBaseDir('etc');
        $doctrineMigrationsBaseDirectory = \Mage::getBaseDir('app') . DS . "database" . DS . $this->getMigrationsNamespace();

        $replacements = [
            $this->getMigrationsNamespace(),
            $this->getMigrationTableName(),
            $doctrineMigrationsBaseDirectory,
        ];

        $code = str_replace($placeHolders, $replacements, $this->getTemplate());
        $code = preg_replace('/^ +$/m', '', $code);

        $fileSystem = new Filesystem();

        if ($fileSystem->exists($migrationsConfigSaveLocation)) {
            try {
                $migrationsSavePath = $migrationsConfigSaveLocation . DS . "migrations.yml";
                $fileSystem->dumpFile($migrationsSavePath, $code);

                return $migrationsSavePath;
            } catch(IOExceptionInterface $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                exit;
            }
        }
        else {
            $output->writeln([
                "<info>Trying to write migration.yml file.</info>",
                "<error>{$migrationsConfigSaveLocation} does not exist.</error>",
            ]);
        }

    }

    /**
     * @param $_migration_table_name
     */
    protected function getMigrationTableName($_migration_table_name) {
        $mageConfig = \Mage::app()->getConfig();
        return strval($mageConfig->getTablePrefix()) . $this::$_migration_table_name;
    }

    /**
     * @return string
     */
    public function getTemplate() {
        return self::$_ymlTemplate;
    }

    protected function configure() {
        $this
            ->setName('etre:migrations:settings:init')
            ->setDescription('This command can be executed if you are seeing missing configuration error messages.')
            ->setHelp(<<<EOT
The <info>%command.name%</info> command generates a configured migration.yml file:
EOT
            );

        parent::configure();
    }
}