<?php

namespace WeCodePixels\TheiaBackupBundle;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Console\Output\OutputInterface;
use WeCodePixels\TheiaBackupBundle\Entity\BackupStatus;

class BackupStatusService
{
    const ERROR_OK = 0;

    const ERROR_NO_BACKUP = 1;

    const ERROR_OLD_BACKUP = 2;

    /* @var ConfigurationService */
    private $configurationService;

    /* @var EntityManager */
    private $em;

    /* @var EntityRepository */
    private $backupStatusRepository;

    public function __construct(ConfigurationService $configurationService, EntityManager $entityManager)
    {
        $this->configurationService = $configurationService;
        $this->em = $entityManager;
        $this->backupStatusRepository = $this->em->getRepository('WeCodePixelsTheiaBackupBundle:BackupStatus');
    }

    /**
     * Get live status of all backups.
     * 
     * @param OutputInterface $output
     * @return array
     */
    public function getStatus(OutputInterface $output)
    {
        $status = array();

        // Get backup configuration.
        $config = $this->configurationService->getConfiguration();
        $backups = $config['backups'];
        unset($config['backups']);

        // Get status for each backup.
        foreach ($backups as $backupId => $backupConfig) {
            $status[$backupId] = $this->getStatusForBackup($output, $backupId, false);
        }

        return $status;
    }

    /**
     * Get status for given backup.
     *
     * @param OutputInterface $output
     * @param $backupId
     * @param $useCachedOutput
     * @return array|null
     */
    public function getStatusForBackup(OutputInterface $output, $backupId, $useCachedOutput)
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

        // Display message.
        $output->writeln("<info>Checking status for backup \"" . $backupConfig['title'] . "\"...</info>");

        // Get status.
        if (array_key_exists('source_mysql', $backupConfig)) {
            $status = $this->executeMysqlStatus($output, $backupId, $backupConfig, $useCachedOutput);
        } else if (array_key_exists('source_postgresql', $backupConfig)) {
            $status = $this->executePostgresqlStatus($output, $backupId, $backupConfig, $useCachedOutput);
        } else if (array_key_exists('source_files', $backupConfig)) {
            $status = $this->executeFilesStatus($output, $backupId, $backupConfig, $useCachedOutput);
        }

        return $status;
    }

    private function executeFilesStatus(OutputInterface $output, $backupId, $config, $useCachedOutput)
    {
        return $this->getDestinationStatus($output, $backupId, $config, $useCachedOutput);
    }

    protected function executeMysqlStatus(OutputInterface $output, $backupId, $config, $useCachedOutput)
    {
        return $this->getDestinationStatus($output, $backupId, $config, $useCachedOutput);
    }

    protected function executePostgresqlStatus(OutputInterface $output, $backupId, $config, $useCachedOutput)
    {
        return $this->getDestinationStatus($output, $backupId, $config, $useCachedOutput);
    }

    protected function getDestinationStatus(OutputInterface $output, $backupId, $config, $useCachedOutput)
    {
        $status = null;

        foreach ($config['destination'] as $destId => $dest) {
            $output->writeln("<comment>\tGetting status for destination \"" . $dest . "\"...</comment>");

            if (!$useCachedOutput) {
                $cmdOutput = $this->getLiveOutput($output, $backupId, $dest, $config);
            }
            else {
                $cmdOutput = $this->getCachedOutput($output, $backupId, $dest, $config);
            }

            if ($output->isVerbose()) {
                $output->writeln("<comment>\tOutput is: \"$cmdOutput\"");
            }

            // Parse output.
            $status = $this->parseOutput($output, $cmdOutput);
        }

        return $status;
    }

    /**
     * Get live output by running duplicity.
     *
     * @param OutputInterface $output
     * @param $backupId
     * @param $dest
     * @param $config
     * @return bool|string
     */
    public function getLiveOutput(OutputInterface $output, $backupId, $dest, $config)
    {
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

        // Save output to database.
        $backupStatus = new BackupStatus();
        $backupStatus->setBackupId($backupId);
        $backupStatus->setDestination($dest);
        $backupStatus->setOutput($cmdOutput);
        $this->em->persist($backupStatus);
        $this->em->flush();

        return $cmdOutput;
    }

    /**
     * Get cached duplicity output from database.
     *
     * @param OutputInterface $output
     * @param $backupId
     * @param $dest
     * @param $config
     * @return string
     */
    public function getCachedOutput(OutputInterface $output, $backupId, $dest, $config)
    {
        $status = $this->backupStatusRepository->findOneBy([
            'backupId' => $backupId,
            'destination' => $dest
        ], [
            'timestamp' => 'DESC'
        ]);

        return $status->getOutput();
    }

    /**
     * Parse duplicity output.
     *
     * @param OutputInterface $output
     * @param $cmdOutput
     * @return array
     */
    public function parseOutput(OutputInterface $output, $cmdOutput)
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

            // Check elapsed time since last backup.
            $errorThreshold = 60 * 60 * 12;
            $elapsedSeconds = time() - $lastBackupTime;
            if ($elapsedSeconds >= $errorThreshold) {
                $output->writeln("\t<error>Backup older than $errorThreshold seconds</error>");
                $error = BackupStatusService::ERROR_OLD_BACKUP;
            }
        } else {
            $output->writeln("\t<error>No backup found</error>");
            $error = BackupStatusService::ERROR_NO_BACKUP;
        }

        $status = array(
            'lastBackupTime' => $lastBackupTime,
            'lastBackupText' => $lastBackupText,
            'lastBackupAge' => $lastBackupAge,
            'error' => $error
        );

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

        return '';
    }
}
