<?php



/*
 * This file is part of Contao.
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;

use Contao\BackendAlerts;
use Contao\BackendConfirm;
use Contao\BackendFile;
use Contao\BackendHelp;
use Contao\BackendIndex;
use Contao\BackendMain;
use Contao\BackendPage;
use Contao\BackendPassword;
use Contao\BackendPopup;
use Contao\BackendPreview;
use Contao\BackendSwitch;
use Contao\CoreBundle\Picker\PickerConfig;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class BackendPasswordRecoveryController
 * @package Markocupic\BackendPasswordRecovery\Controller
 * @Route(defaults={"_scope" = "backend"})
 */
class BackendPasswordRecoveryController extends Controller
{

    /**
     * Handles backendpasswordrecovery requests.
     * @return JsonResponse
     * @Route("/backendpasswordrecovery", name="backend_password_recovery")
     */
    public function sendlinkAction()
    {
        $this->container->get('contao.framework')->initialize();

        $controller = new BackendMain();

        return $controller->run();
    }

    /**
     * @return Response
     *
     * @Route("/backendpasswordrecovery/enterpassword", name="backend_password_recovery")
     */
    public function mainAction()
    {
        $this->container->get('contao.framework')->initialize();

        $controller = new BackendMain();

        return $controller->run();
    }

}
