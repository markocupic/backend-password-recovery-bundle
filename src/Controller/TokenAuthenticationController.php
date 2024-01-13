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
use Symfony\Component\Routing\RouterInterface;

#[Route('/_backend_password_recovery/token_authentication/{_token}', name: self::ROUTE, defaults: ['_scope' => 'backend'])]
class TokenAuthenticationController extends AbstractController
{
    public const ROUTE = 'backend_password_recovery.token_authentication';

    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function __invoke(Request $request, string $_token): Response
    {
        // This will show the password recovery link
        $session = $request->getSession();
        $session->start();
        $session
            ->getFlashBag()
            ->set('_show_password_recovery_link', 'true')
        ;

        // Fallback: Redirect user to the Contao backend login form
        $url = $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new RedirectResponse($url);
    }
}
