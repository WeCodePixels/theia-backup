<?php

namespace WeCodePixels\TheiaBackupBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WeCodePixels\TheiaBackupBundle\ConfigurationService;
use mysqli;

class BackupCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('theia_backup:backup')
            ->setDescription('Execute backups');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var ConfigurationService */
        $configurationService = $this->getContainer()->get('wecodepixels.theia_backup.configuration_service');
        $config = $configurationService->getConfiguration();
        $backups = $config['backups'];
        unset($config['backups']);

        foreach ($backups as $backupConfig) {
            // Use global settings to fill in the missing local ones.
            $backupConfig = array_merge($config, $backupConfig);
            $backupConfig = $configurationService->parseConfig($backupConfig);

            if (array_key_exists('source_files', $backupConfig)) {
                $this->executeFilesBackup($input, $output, $backupConfig);
            } else if (array_key_exists('source_mysql', $backupConfig)) {
                $this->executeMysqlBackup($input, $output, $backupConfig);
            } else if (array_key_exists('source_postgresql', $backupConfig)) {
                $this->executePostgresqlBackup($input, $output, $backupConfig);
            }
        }
    }

    private function executeFilesBackup(InputInterface $input, OutputInterface $output, $config)
    {
        $filesConfig = $config['source_files'];

        $output->writeln("<info>Executing backup for \"" . $filesConfig['path'] . "\"</info>");

        // Partial or full backup
        $full = '';
        if (date('j') == 1) { // Full backup on the first day of the month.
            $full = 'full';
        }

        // Additional arguments.
        if (array_key_exists('additional_args', $filesConfig)) {
            $additional_args = $filesConfig['additional_args'];
        }
        else {
            $additional_args = '';
        }

        // Backup to each destination.
        foreach ($config['destination'] as $dest) {
            // Remove old backups.
            if ($config['remove_older_than']) {
                $output->writeln("\t<comment>Removing old backups from \"" . $dest . "\"</comment>");
                $cmd = "
                    " . $config['duplicity_credentials_cmd'] . "
                    duplicity \
                        remove-older-than " . escapeshellarg($config['remove_older_than']) . " \
                        --force \
                        " . $config['additional_options'] . " \
                        " . escapeshellarg($dest) . " \
                        2>&1
                ";

                if ($output->isVerbose()) {
                    $output->writeln("<comment>\tExecuting \"$cmd\"</comment>");
                    passthru($cmd);
                } else {
                    shell_exec($cmd);
                }
            }

            // Backup files
            {
                $output->writeln("\t<comment>Backing up to \"" . $dest . "\"</comment>");
                $cmd = "
                    " . $config['duplicity_credentials_cmd'] . "
                    duplicity \
                        $full \
                        $additional_args \
                        --volsize=250 \
                        --s3-use-new-style \
                        " . $config['additional_options'] . " \
                        " . ($config['allow_source_mismatch'] ? "--allow-source-mismatch" : "") . " \
                        " . escapeshellarg($filesConfig['path']) . " " . escapeshellarg($dest) . " \
                        2>&1
                ";

                if ($output->isVerbose()) {
                    $output->writeln("<comment>\tExecuting \"$cmd\"</comment>");
                    passthru($cmd);
                } else {
                    shell_exec($cmd);
                }
            }
        }

        return true;
    }

    private function executeMysqlBackup(InputInterface $input, OutputInterface $output, $config)
    {
        $mysqlConfig = $config['source_mysql'];

        $output->writeln("<info>Executing MySQL backup called \"" . $config['title'] . "\"</info>");

        // Get a temporary directory.
        $temporaryDir = $this->getTemporaryDirectory($output, $config);
        if ($temporaryDir == false) {
            return false;
        }

        // Get command for excluding tables.
        $excludeTablesCmd = '';
        foreach ($mysqlConfig['exclude_tables'] as $table) {
            $excludeTablesCmd .= ' --ignore-table=' . $table;
        }

        // Create local backups.
        $mysqli = new mysqli($mysqlConfig['hostname'], $mysqlConfig['username'], $mysqlConfig['password'], "", $mysqlConfig['port']);
        $result = $mysqli->query("SHOW DATABASES");
        while ($row = $result->fetch_assoc()) {
            $database = $row['Database'];

            // Skip if this is an excluded database.
            if (in_array($database, $mysqlConfig['exclude_databases'])) {
                continue;
            }

            // Get output file name.
            $temporaryFile = $string = preg_replace('/[^a-zA-Z0-9]/', '_', $database);
            $temporaryFile = $temporaryDir . '/' . $temporaryFile . '.sql';

            $output->writeln("\t<comment>Creating local backup of database \"" . $database . "\"...</comment>");

            // Run command.
            $cmdHostname = $mysqlConfig['hostname'] != '' ? '-h' . $mysqlConfig['hostname'] : '';
            $cmdPort = $mysqlConfig['port'] != '' ? '-P' . $mysqlConfig['port'] : '';
            $cmdUsername = $mysqlConfig['username'] != '' ? '-u' . $mysqlConfig['username'] : '';
            $cmdPassword = $mysqlConfig['password'] != '' ? '-p' . $mysqlConfig['password'] : '';
            $cmd = "mysqldump $cmdHostname $cmdPort $cmdUsername $cmdPassword $excludeTablesCmd $database > '$temporaryFile'";
            if ($output->isVerbose()) {
                $output->writeln("\t\tExecuting \"$cmd\"");
            }
            shell_exec($cmd);
        }

        // Upload backups.
        unset($config['source_mysql']);
        $config['source_files'] = array(
            'path' => $temporaryDir
        );
        $config['allow_source_mismatch'] = true;
        $this->executeFilesBackup($input, $output, $config);

        // Remove local backups.
        $output->writeln("\t<comment>Removing local backups...</comment>");
        $cmd = "rm -rf " . escapeshellarg($temporaryDir);
        if ($output->isVerbose()) {
            $output->writeln("\t\tExecuting \"$cmd\"");
        }
        shell_exec($cmd);

        return true;
    }

    private function executePostgresqlBackup(InputInterface $input, OutputInterface $output, $config)
    {
        $postgresqlConfig = $config['source_postgresql'];

        $output->writeln("<info>Executing PostgreSQL backup called \"" . $config['title'] . "\...</info>");

        // Get a temporary directory.
        $temporaryDir = $this->getTemporaryDirectory($output, $config);
        if ($temporaryDir == false) {
            return false;
        }

        // Get backup file name.
        $temporaryFile = $temporaryDir . '/' . $postgresqlConfig['filename'];

        // Create local backups.
        $cmd = $postgresqlConfig['cmd'] . ' > ' . escapeshellarg($temporaryFile);
        if ($output->isVerbose()) {
            $output->writeln("\t\tExecuting \"$cmd\"...");
        }
        $cmdOutput = $this->executeCommand($cmd);
        if ($cmdOutput['stderr']) {
            $output->writeln("\t\t<error>stderr output is: \"" . trim($cmdOutput['stderr']) . "\"</error>");

            return false;
        }

        // Upload backups.
        unset($config['source_postgresql']);
        $config['source_files'] = array(
            'path' => $temporaryDir
        );
        $config['allow_source_mismatch'] = true;
        $this->executeFilesBackup($input, $output, $config);

        // Remove local backups.
        $output->writeln("\t\t<comment>Removing local backups...</comment>");
        $cmd = "rm -rf " . escapeshellarg($temporaryDir);
        if ($output->isVerbose()) {
            $output->writeln("\t\tExecuting \"$cmd\"");
        }
        shell_exec($cmd);

        return true;
    }

    protected function getTemporaryDirectory(OutputInterface $output, $config)
    {
        $temporaryDir = trim(shell_exec('mktemp -d -p ' . escapeshellarg($config['temp_dir'])));

        if (in_array($temporaryDir, array("", "/", "/tmp", "/var/tmp"))) {
            $output->writeln("\t<error>Could not create temporary directory. Aborting.</error>");

            return false;
        }

        return $temporaryDir;
    }

    protected function executeCommand($cmd)
    {
        $descriptorspec = array(
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w"),  // stderr
        );

        $proc = proc_open($cmd, $descriptorspec, $pipes);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($proc);

        return array(
            'stdout' => $stdout,
            'stderr' => $stderr
        );
    }
}
