<?php

namespace WeCodePixels\TheiaBackupBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('WeCodePixelsTheiaBackupBundle:Default:index.html.twig', array('name' => $name));
    }
}
