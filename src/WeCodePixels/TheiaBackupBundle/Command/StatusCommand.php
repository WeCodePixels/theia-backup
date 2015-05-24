<?php

namespace WeCodePixels\TheiaBackupBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WeCodePixels\TheiaBackupBundle\ConfigurationService;

class StatusCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('theia_backup:status')
            ->setDescription('Get status of backups');
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

	        if (array_key_exists('source_mysql', $backupConfig)) {
		        $this->executeMysqlStatus($input, $output, $backupConfig);
	        }
	        else if (array_key_exists('source_files', $backupConfig)) {
	            $this->executeFilesStatus($input, $output, $backupConfig);
	        }
        }
    }

    private function executeFilesStatus(InputInterface $input, OutputInterface $output, $config)
    {
        $output->writeln("<info>Checking status for files at \"" . $config['source_files'] . "\"...</info>");

	    $this->checkBackup($input, $output, $config);
    }

    private function executeMysqlStatus(InputInterface $input, OutputInterface $output, $config)
    {
	    $mysqlConfig = $config['source_mysql'];

        $output->writeln("<info>Checking status for MySQL database at host \"" . $mysqlConfig['hostname'] . "\"...</info>");

	    $this->checkBackup($input, $output, $config);
    }

    private function checkBackup(InputInterface $input, OutputInterface $output, $config)
    {
        foreach ($config['destination'] as $dest) {
            $output->writeln("<comment>\tGetting status for destination \"" . $dest . "\"...</comment>");
            $cmd = "
                " . $config['duplicity_credentials_cmd'] . "
                duplicity \
                    collection-status \
                    --s3-use-new-style \
                    " . $config['additional_options'] . " \
                    " . escapeshellarg($dest) . " \
                    2>&1
            ";

            $cmdOutput = shell_exec($cmd);

            if ($output->isVerbose()) {
                $output->writeln("<comment>\tExecuting \"$cmd\"</comment>");
                $output->writeln($cmdOutput);
            } else {
                $cmdOutput = shell_exec($cmd);
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
        }
    }
}
