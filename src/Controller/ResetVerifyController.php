<?php

declare(strict_types=1);

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;

use Contao\CoreBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/_backend_password_recovery/reset_verify/{_token}', name: self::ROUTE, defaults: ['_scope' => 'backend'])]
class ResetVerifyController extends AbstractController
{
    public const ROUTE = 'backend_password_recovery.reset_verify';

    public function __invoke(Request $request, string $_token): Response
    {
        // Under normal circumstances,
        // this point should never be reached
        // because the authenticator class should automatically
        // redirect the user to the password recovery form
        // after successful token verification.
        //
        // This method is therefore only used as a fallback.
        // If token verification fails
        // the user will be redirected to the backend login page.

        // This will show the password recovery link
        $session = $request->getSession();
        $session->start();
        $session
            ->getFlashBag()
            ->set('_show_password_recovery_link', 'true')
        ;

        $url = $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new RedirectResponse($url);
    }
}
