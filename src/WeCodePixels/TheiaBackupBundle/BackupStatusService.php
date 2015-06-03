<?php

namespace WeCodePixels\TheiaBackupBundle;

use Symfony\Component\Console\Output\OutputInterface;

class BackupStatusService
{
    const ERROR_OK = 0;

    const ERROR_NO_BACKUP = 1;

    const ERROR_OLD_BACKUP = 2;

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

    public function getStatusForBackup($backupId, OutputInterface $output)
    {
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
            {
                $lastBackupTime = null;
                $lastBackupText = null;
                $lastBackupAge = null;
                $error = BackupStatusService::ERROR_OK;

                // Find the last occurrence since there can be multiple chains in the backup.
                $str = 'Chain end time: ';
                $pos = strrpos($cmdOutput, $str);

                if ($pos) {
                    $lastBackupTime = substr($cmdOutput, strlen($str) + $pos, strpos($cmdOutput, "\n", $pos) - strlen($str) - $pos);
                }

                if ($lastBackupTime) {
                    $lastBackupTime = strtotime($lastBackupTime);
                    $lastBackupAge = $this->getElapsedTime($lastBackupTime);

                    // Output human-readable date.
                    $lastBackupText = date("H:i:s d-m-y", $lastBackupTime);
                    $output->writeln("\t<comment>Last backup time: $lastBackupText ($lastBackupAge)</comment>");

                    // Check elapsed time since last backup
                    $errorThreshold = 0 * 60 * 60 * 12;
                    $elapsedSeconds = time() - $lastBackupTime;
                    if ($elapsedSeconds >= $errorThreshold) {
                        $output->writeln("\t<error>Backup older than $errorThreshold seconds</error>");
                        $error = BackupStatusService::ERROR_OLD_BACKUP;
                    }
                } else {
                    $output->writeln("\t<error>No backup found</error>");
                    $error = BackupStatusService::ERROR_NO_BACKUP;
                }
            }

            $status = array(
                'lastBackupTime' => $lastBackupTime,
                'lastBackupText' => $lastBackupText,
                'lastBackupAge' => $lastBackupAge,
                'error' => $error
            );
        }

        return $status;
    }

    /**
     * Returns a rought approximation of the number of days/hours/minutes/etc. that have passed
     * Taken from http://stackoverflow.com/a/14339355/148388
     * @param int $pastTime A Unix timestamp.
     * @return string
     */
    protected function getElapsedTime($pastTime)
    {
        $currentTime = time() - $pastTime;

        if ($currentTime < 1) {
            return '0 seconds';
        }

        $a = array(
            365 * 24 * 60 * 60 => 'year',
            30 * 24 * 60 * 60 => 'month',
            24 * 60 * 60 => 'day',
            60 * 60 => 'hour',
            60 => 'minute',
            1 => 'second'
        );
        $aPlural = array(
            'year' => 'years',
            'month' => 'months',
            'day' => 'days',
            'hour' => 'hours',
            'minute' => 'minutes',
            'second' => 'seconds'
        );

        foreach ($a as $secs => $str) {
            $d = $currentTime / $secs;

            if ($d >= 1) {
                $r = round($d);

                return $r . ' ' . ($r > 1 ? $aPlural[$str] : $str) . ' ago';
            }
        }
    }
}
