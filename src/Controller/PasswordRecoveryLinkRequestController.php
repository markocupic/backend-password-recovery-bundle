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
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Util\LocaleUtil;
use Contao\Email;
use Contao\Environment;
use Contao\Message;
use Contao\System;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
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
    public const CONTAO_LOG_PW_RECOVERY_REQUEST = 'BE_PW_RECOVERY_REQUEST';

    private Adapter $config;
    private Adapter $contaoCoreBundle;
    private Adapter $environment;
    private Adapter $localUtil;
    private Adapter $message;
    private Adapter $system;
    private Adapter $uuid;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly int $tokenLifetime,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->config = $this->framework->getAdapter(Config::class);
        $this->contaoCoreBundle = $this->framework->getAdapter(ContaoCoreBundle::class);
        $this->localUtil = $this->framework->getAdapter(LocaleUtil::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->system = $this->framework->getAdapter(System::class);
        $this->uuid = $this->framework->getAdapter(Uuid::class);
    }

    #[Route('/backendpasswordrecovery/requirepasswordrecoverylink/form', name: 'backend_password_recovery_requirepasswordrecoverylink_form')]
    public function requirepasswordrecoverylinkAction(): Response
    {
        $this->initializeContaoFramework();

        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->uriSigner->check($request->getUri())) {
            return new Response('Access denied!', Response::HTTP_FORBIDDEN);
        }

        $this->system->loadLanguageFile('default');
        $this->system->loadLanguageFile('modules');

        if ('tl_require_password_link_form' === $request->request->get('FORM_SUBMIT') && '' !== $request->request->get('usernameOrEmail')) {
            $usernameOrEmail = $request->request->get('usernameOrEmail');
            $now = time();

            $rowUser = $this->connection->fetchAssociative(
                "SELECT * FROM tl_user WHERE (email LIKE ? OR username = ?) AND disable = '' AND (start = '' OR start < $now) AND (stop = '' OR stop > $now)",
                [
                    $usernameOrEmail,
                    $usernameOrEmail,
                ],
            );

            if (!$rowUser) {
                $this->message->addError($this->translator->trans('ERR.pwRecoveryFailed', [], 'contao_default'));
            } else {
                $token = $this->uuid->uuid4()->toString();
                $set = [
                    'pwResetToken' => $token,
                    'pwResetLifetime' => time() + $this->tokenLifetime, // Default 600 (10 min)
                ];

                $this->connection->update('tl_user', $set, ['id' => $rowUser['id']]);

                // Generate password recovery link
                $strLink = $this->router->generate(
                    'backend_password_recovery_renewpassword',
                    ['token' => base64_encode($token)],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );

                // Send email with password recovery link to the user
                $email = new Email();
                $email->from = $GLOBALS['TL_ADMIN_EMAIL'] ?? $this->config->get('adminEmail');

                // Email: subject
                $strSubject = str_replace('#host#', $this->environment->get('base'), $this->translator->trans('MSC.pwRecoveryEmailSubject', [], 'contao_default'));
                $email->subject = $strSubject;

                // Email: text
                $strText = str_replace('#host#', $this->environment->get('base'), $this->translator->trans('MSC.pwRecoveryEmailText', [], 'contao_default'));
                $strText = str_replace('#link#', $strLink, $strText);
                $strText = str_replace('#name#', $rowUser['name'], $strText);
                $strText = str_replace('#lifetime#', (string) floor($this->tokenLifetime / 60), $strText);
                $email->text = $strText;

                // Send the email
                $email->sendTo($rowUser['email']);

                // Add a log entry to Contao system log.
                $this->contaoGeneralLogger?->info(
                    sprintf('Password recovery link has been sent to backend user "%s" ID %d.', $rowUser['username'], $rowUser['id']),
                    ['contao' => new ContaoContext(__METHOD__, static::CONTAO_LOG_PW_RECOVERY_REQUEST)]
                );

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

        $this->system->loadLanguageFile('default');
        $this->system->loadLanguageFile('modules');

        $objTemplate = new BackendTemplate('be_password_recovery_link_request');
        $objTemplate->showConfirmation = true;
        $objTemplate->backHref = $this->router->generate('contao_backend_login');
        $this->setUpTemplate($objTemplate);

        return $objTemplate->getResponse();
    }

    private function setUpTemplate(BackendTemplate &$objTemplate): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $objTemplate->theme = $this->backend->getTheme();
        $objTemplate->messages = $this->message->generate();
        $objTemplate->base = $this->environment->get('base');
        $objTemplate->language = $this->localUtil->formatAsLanguageTag($request->getLocale());
        $objTemplate->host = $this->backend->getDecodedHostname();
        $objTemplate->charset = $this->config->get('characterSet');

        if (version_compare($this->contaoCoreBundle->getVersion(), '5.0', 'lt')) {
            $objTemplate->requestToken = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        }
    }
}
