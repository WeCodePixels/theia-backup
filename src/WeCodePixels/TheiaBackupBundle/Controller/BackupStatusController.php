<?php

namespace WeCodePixels\TheiaBackupBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use WeCodePixels\TheiaBackupBundle\BackupStatusService;

class BackupStatusController extends Controller
{
    public function indexAction($backupId)
    {
        $output = new BufferedOutput();
        $output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        /* @var BackupStatusService */
        $backupStatusService = $this->get('wecodepixels.theia_backup.backup_status_service');
        $status = $backupStatusService->getStatusForBackup($backupId ,$output);

        return new JsonResponse(array(
            'status' => $status,
            'output' => $output->fetch()
        ));
    }
}
