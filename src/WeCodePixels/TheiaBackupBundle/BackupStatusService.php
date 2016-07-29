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

    /**
     * @param OutputInterface $output
     * @param $backupId
     * @param $config
     * @param $useCachedOutput
     * @return null|BackupStatus
     */
    protected function getDestinationStatus(OutputInterface $output, $backupId, $config, $useCachedOutput)
    {
        /* @var BackupStatus */
        $status = null;

        foreach ($config['destination'] as $destId => $dest) {
            $output->writeln("<comment>\tGetting status for destination \"" . $dest . "\"...</comment>");

            if (!$useCachedOutput) {
                $status = $this->getLiveOutput($output, $backupId, $dest, $config);
            } else {
                $status = $this->getCachedOutput($output, $backupId, $dest);
            }

            if ($output->isVerbose()) {
                $output->writeln("<comment>\tOutput is: \"" . $status->getOutput() . "\"");
            }

            // Parse output.
            $this->parseOutput($output, $status);
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
     * @return BackupStatus
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

                return null;
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

        $status = new BackupStatus();
        $status->setBackupId($backupId);
        $status->setDestination($dest);
        $status->setOutput($cmdOutput);

        // Save status to database.
        $this->em->persist($status);
        $this->em->flush();

        return $status;
    }

    /**
     * Get cached duplicity output from database.
     *
     * @param OutputInterface $output
     * @param $backupId
     * @param $dest
     * @return BackupStatus
     */
    public function getCachedOutput(OutputInterface $output, $backupId, $dest)
    {
        $status = $this->backupStatusRepository->findOneBy([
            'backupId' => $backupId,
            'destination' => $dest
        ], [
            'timestamp' => 'DESC'
        ]);

        return $status;
    }

    /**
     * Parse duplicity output.
     *
     * @param OutputInterface $output
     * @param BackupStatus $status
     */
    public function parseOutput(OutputInterface $output, BackupStatus $status)
    {
        $status->lastBackupTime = null;
        $status->lastBackupText = null;
        $status->lastBackupAge = null;
        $status->error = BackupStatusService::ERROR_OK;

        // Find the last occurrence since there can be multiple chains in the backup.
        $str = 'Chain end time: ';
        $pos = strrpos($status->getOutput(), $str);

        if ($pos) {
            $status->lastBackupTime = substr($status->getOutput(), strlen($str) + $pos, strpos($status->getOutput(), "\n", $pos) - strlen($str) - $pos);
        }

        if ($status->lastBackupTime) {
            $status->lastBackupTime = strtotime($status->lastBackupTime);
            $status->lastBackupAge = Misc::getElapsedTime($status->lastBackupTime);

            // Output human-readable date.
            $status->lastBackupText = Misc::getTextForTimestamp($status->lastBackupTime);
            $output->writeln("\t<comment>Last backup time: $status->lastBackupText ($status->lastBackupAge)</comment>");

            // Check elapsed time since last backup.
            $errorThreshold = 60 * 60 * 12;
            $elapsedSeconds = time() - $status->lastBackupTime;
            if ($elapsedSeconds >= $errorThreshold) {
                $output->writeln("\t<error>Backup older than $errorThreshold seconds</error>");
                $status->error = BackupStatusService::ERROR_OLD_BACKUP;
            }
        } else {
            $output->writeln("\t<error>No backup found</error>");
            $status->error = BackupStatusService::ERROR_NO_BACKUP;
        }
    }
}
