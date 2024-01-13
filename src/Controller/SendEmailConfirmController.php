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

use Contao\BackendTemplate;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

#[Route('/_backend_password_recovery/confirm', name: self::ROUTE, defaults: ['_scope' => 'backend', '_token_check' => true])]
class SendEmailConfirmController extends AbstractController
{
    use BackendTemplateTrait;

    public const ROUTE = 'backend_password_recovery.send_email_confirm';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RouterInterface $router,
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->initializeContaoFramework();

        $systemAdapter = $this->framework->getAdapter(System::class);

        if (!$this->uriSigner->checkRequest($request)) {
            return new Response('Bad request. Access denied!', Response::HTTP_FORBIDDEN);
        }

        $systemAdapter->loadLanguageFile('default');
        $systemAdapter->loadLanguageFile('modules');

        $objTemplate = new BackendTemplate('be_password_recovery_confirm');
        $objTemplate->backHref = $this->router->generate('contao_backend_login');
        $this->addMoreDataToTemplate($objTemplate, $request, $this->framework);

        return $objTemplate->getResponse();
    }
}
