<?php

namespace App\Command;

use Exception;
use PDO;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command
 */
class InstallCommand extends AbstractCommand
{

    /**
     * Configure
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('environment', 'e', InputOption::VALUE_OPTIONAL, 'The target environment.');

        $this->setName('install');
        $this->setDescription('Install a new application');
    }

    /**
     * Execute command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int integer 0 on success, or an error code.
     * @throws Exception
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $settings = $this->container->get('settings');
        $root = $settings['root'];
        $configPath = $root . '/config';

        $output->writeln('Create env.php');
        copy($configPath . '/env.example.php', $configPath . '/env.php');

        $output->writeln('Generate random app secret');
        file_put_contents($configPath . '/default.php', str_replace('{{app_secret}}', bin2hex(random_bytes(20)), file_get_contents($configPath . '/default.php')));

        $env = '';
        if ($input->hasOption('environment')) {
            $env = $input->getOption('environment');
        };

        if ($env == 'travis') {
            $mySqlHost = '127.0.0.1';
            $mySqlDatabase = 'test';
            $mySqlUsername = 'root';
            $mySqlPassword = '';
        } else {
            // MySQL setup
            if (!$mySqlHost = $io->ask('Enter MySQL host', '127.0.0.1')) {
                $output->writeln('Aborted');
                return 1;
            }
            if (!$mySqlDatabase = $io->ask('Enter MySQL database name', 'prisma')) {
                $output->writeln('Aborted');
                return 1;
            }

            $mySqlUsername = $io->ask('Enter MySQL username:', 'root');
            $mySqlPassword = $io->ask('Enter MySQL password:', '', function ($string) {
                return $string ? $string: '';
            });
        }
        try {
            $output->writeln('Create database: ' . $mySqlDatabase);
            $pdo = new PDO("mysql:host=$mySqlHost;charset=utf8", $mySqlUsername, $mySqlPassword,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8 COLLATE utf8_unicode_ci"
                )
            );

            $mySqlDatabaseQuoted = "`" . str_replace("`", "``", $mySqlDatabase) . "`";
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $mySqlDatabaseQuoted;");

            $output->writeln('Update development configuration');
            file_put_contents($configPath . '/development.php', str_replace('{{db_database}}', $mySqlDatabase, file_get_contents($configPath . '/development.php')));
            file_put_contents($configPath . '/env.php', str_replace('{{db_username}}', $mySqlUsername, file_get_contents($configPath . '/env.php')));
            file_put_contents($configPath . '/env.php', str_replace('{{db_password}}', $mySqlPassword, file_get_contents($configPath . '/env.php')));

            $output->writeln('Install database tables');

            $pdo->exec("USE $mySqlDatabaseQuoted;");

            chdir($root);
            system('php cli.php phinx migrate');

            $output->writeln('<info>Setup successfully<info>');
            return 0;
        } catch (PDOException $ex) {
            $output->writeln(sprintf('<error>Database error: %s</error> ', $ex->getMessage()));
            return 1;
        } catch (Exception $exception) {
            $output->writeln(sprintf('<error>Unknown error: %s</error> ', $exception->getMessage()));
            return 1;
        }
    }
}
