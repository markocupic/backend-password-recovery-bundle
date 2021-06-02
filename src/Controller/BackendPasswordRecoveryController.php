<?php

declare(strict_types=1);

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;

use Markocupic\BackendPasswordRecoveryBundle\BackendPassword;
use Markocupic\BackendPasswordRecoveryBundle\RequirePasswordRecoveryLink;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class BackendPasswordRecoveryController.
 *
 * @Route(defaults={"_scope" = "backend", "_token_check" = true})
 */
class BackendPasswordRecoveryController extends AbstractController
{
    /**
     * @Route("/backendpasswordrecovery/requirepasswordrecoverylink", name="backend_password_recovery_requirepasswordrecoverylink")
     */
    public function requirepasswordrecoverylinkAction(): Response
    {
        $this->container->get('contao.framework')->initialize();
        $controller = new RequirePasswordRecoveryLink();

        return $controller->run();
    }

    /**
     * @Route("/backendpasswordrecovery/renewpassword", name="backend_password_recovery_renewpassword")
     */
    public function renewpasswordAction(): Response
    {
        $this->container->get('contao.framework')->initialize();
        $controller = new BackendPassword();

        return $controller->run();
    }
}
