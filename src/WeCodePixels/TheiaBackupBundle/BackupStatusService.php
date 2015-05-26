<?php

namespace WeCodePixels\TheiaBackupBundle;

use Symfony\Component\Console\Output\OutputInterface;

class BackupStatusService
{
    /* @var ConfigurationService */
    private $configurationService;

    public function __construct(ConfigurationService $configurationService)
    {
        $this->configurationService = $configurationService;
    }

    public function getStatus(OutputInterface $output)
    {
        $status = array();

        // Get backup configuration.
        $config = $this->configurationService->getConfiguration();
        $backups = $config['backups'];
        unset($config['backups']);

        // Get status for each backup.
        foreach ($backups as $backupId => $backupConfig) {
            $status[$backupId] = $this->getStatusForBackup($backupId, $output);
        }

        return $status;
    }

    public function getStatusForBackup($backupId, OutputInterface $output) {
        $status = null;

        // Get backup configuration.
        $config = $this->configurationService->getConfiguration();
        $backups = $config['backups'];
        unset($config['backups']);

        // Get status for given backup ID.
        $backupConfig = $backups[$backupId];

        // Use global settings to fill in the missing local ones and validate existing ones.
        $backupConfig = array_merge($config, $backupConfig);
        $backupConfig = $this->configurationService->parseConfig($backupConfig);

        if (array_key_exists('source_mysql', $backupConfig)) {
            $status = $this->executeMysqlStatus($output, $backupConfig);
        } else if (array_key_exists('source_postgresql', $backupConfig)) {
            $status = $this->executePostgresqlStatus($output, $backupConfig);
        } else if (array_key_exists('source_files', $backupConfig)) {
            $status = $this->executeFilesStatus($output, $backupConfig);
        }

        return $status;
    }

    private function executeFilesStatus(OutputInterface $output, $config)
    {
        $output->writeln("<info>Checking status for files at \"" . $config['source_files'] . "\"...</info>");

        return $this->getDestinationStatus($output, $config);
    }

    protected function executeMysqlStatus(OutputInterface $output, $config)
    {
        $mysqlConfig = $config['source_mysql'];
        $output->writeln("<info>Checking status for MySQL database...</info>");

        return $this->getDestinationStatus($output, $config);
    }

    protected function executePostgresqlStatus(OutputInterface $output, $config)
    {
        $postgresqlConfig = $config['source_postgresql'];
        $output->writeln("<info>Checking status for PostgreSQL database...</info>");

        return $this->getDestinationStatus($output, $config);
    }

    protected function getDestinationStatus(OutputInterface $output, $config)
    {
        $status = null;

        foreach ($config['destination'] as $destId => $dest) {
            $output->writeln("<comment>\tGetting status for destination \"" . $dest . "\"...</comment>");

            // Check permissions for local files.
            if (substr($dest, 0, 7) == 'file://') {
                $path = substr($dest, 7);
                $cmd = "cd " . escapeshellarg($path) . "; tail -n 1 *.manifest 2>&1";
                $cmdOutput = shell_exec($cmd);
                if (strstr($cmdOutput, 'Permission denied') !== false) {
                    $output->writeln("\t<error>Cannot access destination files - Permission denied</error>");

                    return false;
                }
            }

            $cmd = "
                duplicity \
                    collection-status \
                    --s3-use-new-style \
                    " . $config['additional_options'] . " \
                    " . escapeshellarg($dest) . " \
                    2>&1
            ";

            if ($output->isVerbose()) {
                $output->writeln("<comment>\tExecuting \"$cmd\" (credentials not shown)...</comment>");
            }

            $cmd = $config['duplicity_credentials_cmd'] . " " . $cmd;
            $cmdOutput = shell_exec($cmd);

            if ($output->isVerbose()) {
                $output->writeln("<comment>\tOutput is: \"$cmdOutput\"");
            }

            // Parse output.
            $str = 'Chain end time: ';
            $pos = strpos($cmdOutput, $str);
            $lastBackupTime = null;
            if ($pos) {
                $lastBackupTime = substr($cmdOutput, strlen($str) + $pos, strpos($cmdOutput, "\n", $pos) - strlen($str) - $pos);
            }
            if ($lastBackupTime) {
                $lastBackupTime = strtotime($lastBackupTime);
                $lastBackupTime = date("H:i:s d-m-y", $lastBackupTime) . ' (' . round((time() - $lastBackupTime) / 60 / 60 / 24) . ' days ago)';
                $output->writeln("\t<comment>Last backup time: $lastBackupTime</comment>");
            } else {
                $output->writeln("\t<error>No backup found</error>");
            }

            $status = array(
                'lastBackupTime' => $lastBackupTime
            );
        }

        return $status;
    }
}


