<?php

namespace WeCodePixels\TheiaBackupBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WeCodePixels\TheiaBackupBundle\BackupStatusService;

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
        /* @var BackupStatusService */
        $backupStatusService = $this->getContainer()->get('wecodepixels.theia_backup.backup_status_service');
        $backupStatusService->getStatus($output);
    }

}
