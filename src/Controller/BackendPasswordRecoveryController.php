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


use Markocupic\BackendPasswordRecoveryBundle\BackendPassword;
use Markocupic\BackendPasswordRecoveryBundle\RequirePasswordRecoveryLink;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;



/**
 * Class BackendPasswordRecoveryController
 * @package Markocupic\BackendPasswordRecovery\Controller
 *
 * @Route(defaults={"_scope" = "backend", "_token_check" = true})
 */
class BackendPasswordRecoveryController extends Controller
{

    /**
     *
     * @return Response
     *
     * @Route("/backendpasswordrecovery/requirepasswordrecoverylink", name="backend_password_recovery_requirepasswordrecoverylink")
     */
    public function requirepasswordrecoverylinkAction(): Response
    {
        $this->container->get('contao.framework')->initialize();
        $controller = new RequirePasswordRecoveryLink();
        return $controller->run();
    }

    /**
     *
     * @return Response
     *
     * @Route("/backendpasswordrecovery/renewpassword", name="backend_password_recovery_renewpassword")
     */
    public function renewpasswordAction(): Response
    {
        $this->container->get('contao.framework')->initialize();
        $controller = new BackendPassword();
        return $controller->run();
    }

}
