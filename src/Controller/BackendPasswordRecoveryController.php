<?php



/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;


use Contao\BackendMain;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Markocupic\BackendPasswordRecoveryBundle\BackendPassword;

/**
 * Class BackendPasswordRecoveryController
 * @package Markocupic\BackendPasswordRecovery\Controller
 */
class BackendPasswordRecoveryController extends Controller
{



    /**
     * @return Response
     *
     * @Route("/backendpasswordrecovery/{slug}", name="backend_password_recovery")
     */
    public function mainAction($slug)
    {
        $this->container->get('contao.framework')->initialize();

        $controller = new BackendPassword($slug);

        return $controller->run();
    }

}
