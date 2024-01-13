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

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Util\LocaleUtil;
use Contao\Environment;
use Contao\Message;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

#[Route('/_backend_password_recovery/confirm', name: self::ROUTE, defaults: ['_scope' => 'backend', '_token_check' => true])]
class SendEmailConfirmController extends AbstractController
{
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

        $system = $this->framework->getAdapter(System::class);

        if (!$this->uriSigner->check($request->getUri())) {
            return new Response('Bad request. Access denied!', Response::HTTP_FORBIDDEN);
        }

        $system->loadLanguageFile('default');
        $system->loadLanguageFile('modules');

        $objTemplate = new BackendTemplate('be_password_recovery_link_request');
        $objTemplate->showConfirmation = true;
        $objTemplate->backHref = $this->router->generate('contao_backend_login');
        $this->addMoreDataToTemplate($objTemplate, $request);

        return $objTemplate->getResponse();
    }

    private function addMoreDataToTemplate(BackendTemplate $objTemplate, Request $request): void
    {
        $environment = $this->framework->getAdapter(Environment::class);
        $config = $this->framework->getAdapter(Config::class);
        $message = $this->framework->getAdapter(Message::class);
        $localUtil = $this->framework->getAdapter(LocaleUtil::class);
        $backend = $this->framework->getAdapter(Backend::class);

        $objTemplate->theme = $backend->getTheme();
        $objTemplate->messages = $message->generate();
        $objTemplate->base = $environment->get('base');
        $objTemplate->language = $localUtil->formatAsLanguageTag($request->getLocale());
        $objTemplate->host = $backend->getDecodedHostname();
        $objTemplate->charset = $config->get('characterSet');
    }
}
