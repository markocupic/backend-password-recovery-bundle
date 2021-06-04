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

use Contao\CoreBundle\Controller\AbstractController;
use Markocupic\BackendPasswordRecoveryBundle\RequirePasswordRecoveryLink;
use Markocupic\BackendPasswordRecoveryBundle\SendPasswordRecoveryLink;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class SendPasswordRecoveryLinkController.
 *
 * @Route(defaults={"_scope" = "backend", "_token_check" = true})
 */
class SendPasswordRecoveryLinkController extends AbstractController
{
    private $uriSigner;

    private $requestStack;

    public function __construct(UriSigner $uriSigner, RequestStack $requestStack)
    {
        $this->uriSigner = $uriSigner;
        $this->requestStack = $requestStack;
    }

    /**
     * @Route("/backendpasswordrecovery/requirepasswordrecoverylink", name="backend_password_recovery_requirepasswordrecoverylink")
     */
    public function requirepasswordrecoverylinkAction(): Response
    {
        $this->initializeContaoFramework();

        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->uriSigner->check($request->getUri())) {
            return new Response('Access denied!', Response::HTTP_FORBIDDEN);
        }

        $controller = new SendPasswordRecoveryLink();

        return $controller->run();
    }
}
