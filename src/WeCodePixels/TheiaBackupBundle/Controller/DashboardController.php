<?php

namespace WeCodePixels\TheiaBackupBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use WeCodePixels\TheiaBackupBundle\ConfigurationService;

class DashboardController extends Controller
{
    public function indexAction()
    {
        // Get backup configuration.
        {
            /* @var ConfigurationService */
            $configurationService = $this->get('wecodepixels.theia_backup.configuration_service');
            $config = $configurationService->getConfiguration();
            $backups = $config['backups'];
        }

        return $this->render('WeCodePixelsTheiaBackupBundle:Dashboard:index.html.php', array(
            'backups' => $backups
        ));
    }
}
