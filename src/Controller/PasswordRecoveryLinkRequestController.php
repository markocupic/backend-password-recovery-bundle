<?php

declare(strict_types=1);

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
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
use Contao\CoreBundle\Util\LocaleUtil;
use Contao\Email;
use Contao\Environment;
use Contao\Message;
use Contao\System;
use Doctrine\DBAL\Connection;
use Markocupic\BackendPasswordRecoveryBundle\Util\Crypt;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(defaults: ['_scope' => 'backend', '_token_check' => true])]
class PasswordRecoveryLinkRequestController extends AbstractController
{
    public function __construct(
        private readonly Crypt $crypt,
        private readonly Connection $connection,
        private readonly UriSigner $uriSigner,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/backendpasswordrecovery/requirepasswordrecoverylink/form', name: 'backend_password_recovery_requirepasswordrecoverylink_form')]
    public function requirepasswordrecoverylinkAction(): Response
    {
        $this->initializeContaoFramework();

        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->uriSigner->check($request->getUri())) {
            return new Response('Access denied!', Response::HTTP_FORBIDDEN);
        }

        System::loadLanguageFile('default');
        System::loadLanguageFile('modules');

        if ('tl_require_password_link_form' === $request->request->get('FORM_SUBMIT') && '' !== $request->request->get('usernameOrEmail')) {
            $usernameOrEmail = $request->request->get('usernameOrEmail');
            $time = time();

            $rowUser = $this->connection->fetchAssociative(
                "SELECT * FROM tl_user WHERE (email LIKE ? OR username=?) AND disable='' AND (start='' OR start<$time) AND (stop='' OR stop>$time)",
                [
                    $usernameOrEmail,
                    $usernameOrEmail,
                ],
            );

            if (!$rowUser) {
                Message::addError($this->translator->trans('ERR.pwRecoveryFailed', [], 'contao_default'));
            } else {
                // Set renew password token
                $expiry = time() + 900; // 15 min

                $iv = $this->crypt->getInitializationVector();
                $encryptedToken = $this->crypt->encrypt((string) $expiry, $iv);

                $set = [
                    'activation' => sprintf('%s--%s', $iv, $encryptedToken),
                ];

                $this->connection->update('tl_user', $set, ['id' => $rowUser['id']]);

                // Generate renew password link
                $strLink = $this->router->generate(
                    'backend_password_recovery_renewpassword',
                    ['token' => $encryptedToken],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );

                // Send email with password recover link to the user
                $objEmail = new Email();
                $objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'] ?? Config::get('adminEmail');

                // Subject
                $strSubject = str_replace('#host#', Environment::get('base'), $this->translator->trans('MSC.pwRecoveryEmailSubject', [], 'contao_default'));
                $objEmail->subject = $strSubject;

                // Text
                $strText = str_replace('#host#', Environment::get('base'), $this->translator->trans('MSC.pwRecoveryEmailText', [], 'contao_default'));
                $strText = str_replace('#link#', $strLink, $strText);
                $strText = str_replace('#name#', $rowUser['name'], $strText);
                $objEmail->text = $strText;

                // Send
                $objEmail->sendTo($rowUser['email']);

                // Everything ok! Sign the uri & redirect to the confirmation page
                $href = $this->router->generate(
                    'backend_password_recovery_requirepasswordrecoverylink_confirm',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                return $this->redirect($this->uriSigner->sign($href));
            }
        }

        $objTemplate = new BackendTemplate('be_password_recovery_link_request');
        $objTemplate->showForm = true;
        $this->setUpTemplate($objTemplate);

        return $objTemplate->getResponse();
    }

    #[Route('/backendpasswordrecovery/requirepasswordrecoverylink/confirm', name: 'backend_password_recovery_requirepasswordrecoverylink_confirm')]
    public function requirepasswordrecoveryConfirmAction(): Response
    {
        $this->initializeContaoFramework();

        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->uriSigner->check($request->getUri())) {
            return new Response('Access denied!', Response::HTTP_FORBIDDEN);
        }

        System::loadLanguageFile('default');
        System::loadLanguageFile('modules');

        $objTemplate = new BackendTemplate('be_password_recovery_link_request');
        $objTemplate->showConfirmation = true;
        $objTemplate->backHref = $this->router->generate('contao_backend_login');
        $this->setUpTemplate($objTemplate);

        return $objTemplate->getResponse();
    }

    private function setUpTemplate(BackendTemplate &$objTemplate): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $objTemplate->theme = Backend::getTheme();
        $objTemplate->messages = Message::generate();
        $objTemplate->base = Environment::get('base');
        $objTemplate->language = LocaleUtil::formatAsLanguageTag($request->getLocale());
        $objTemplate->host = Backend::getDecodedHostname();
        $objTemplate->charset = Config::get('characterSet');
    }
}
