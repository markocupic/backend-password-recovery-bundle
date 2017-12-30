<?php

/**
 * Backend Password Reoovery Bundle for Contao CMS
 *
 * Copyright (C) 2005-2018 Marko Cupic
 *
 * @package Backend Password Recovery Bundle
 * @link    https://www.github.com/markocupic/backend-password-recovery-bundle
 *
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;


use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Markocupic\BackendPasswordRecoveryBundle\BackendPassword;
use Markocupic\BackendPasswordRecoveryBundle\RequirePasswordRecoveryLink;


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
        if ($slug == 'requirepasswordrecoverylink')
        {
            $this->container->get('contao.framework')->initialize();
            $controller = new RequirePasswordRecoveryLink($slug);
            return $controller->run();
        }

        if ($slug == 'renewpassword')
        {
            $this->container->get('contao.framework')->initialize();
            $controller = new BackendPassword($slug);
            return $controller->run();
        }


    }

}
