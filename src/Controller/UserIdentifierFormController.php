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
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Util\LocaleUtil;
use Contao\Email;
use Contao\Environment;
use Contao\Message;
use Contao\System;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/_backend_password_recovery/form', name: self::ROUTE, defaults: ['_scope' => 'backend', '_token_check' => true])]
class UserIdentifierFormController extends AbstractController
{
    public const ROUTE = 'backend_password_recovery.user_identifier_form';
    public const CONTAO_LOG_PW_RECOVERY_REQUEST = 'BE_PW_RECOVERY_REQUEST';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly int $tokenLifetime,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->initializeContaoFramework();

        // Adapters
        $environment = $this->framework->getAdapter(Environment::class);
        $config = $this->framework->getAdapter(Config::class);
        $system = $this->framework->getAdapter(System::class);
        $uuid = $this->framework->getAdapter(Uuid::class);

        if (!$this->uriSigner->check($request->getUri())) {
            return new Response('Access denied!', Response::HTTP_FORBIDDEN);
        }

        $system->loadLanguageFile('default');
        $system->loadLanguageFile('modules');

        if ('tl_require_password_link_form' === $request->request->get('FORM_SUBMIT') && '' !== $request->request->get('usernameOrEmail')) {
            $usernameOrEmail = $request->request->get('usernameOrEmail');
            $now = time();

            $row = $this->connection->fetchAssociative(
                "SELECT * FROM tl_user WHERE (email LIKE ? OR username = ?) AND disable = '' AND (start = '' OR start < $now) AND (stop = '' OR stop > $now)",
                [
                    $usernameOrEmail,
                    $usernameOrEmail,
                ],
            );

            if ($row) {
                $token = $uuid->uuid4()->toString();

                $set = [
                    'pwResetToken' => $token,
                    'pwResetLifetime' => time() + $this->tokenLifetime, // Default 600 (10 min)
                ];

                $this->connection->update('tl_user', $set, ['id' => $row['id']]);

                // Generate password recovery link
                $strLink = $this->router->generate(
                    TokenAuthenticationController::ROUTE,
                    ['_token' => base64_encode($token)],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );

                // Send email with password recovery link to the user
                $email = new Email();
                $email->from = $GLOBALS['TL_ADMIN_EMAIL'] ?? $config->get('adminEmail');

                // Email: subject
                $strSubject = str_replace('#host#', $environment->get('base'), $this->translator->trans('MSC.pwRecoveryEmailSubject', [], 'contao_default'));
                $email->subject = $strSubject;

                // Email: text
                $strText = str_replace('#host#', $environment->get('base'), $this->translator->trans('MSC.pwRecoveryEmailText', [], 'contao_default'));
                $strText = str_replace('#link#', $strLink, $strText);
                $strText = str_replace('#name#', $row['name'], $strText);
                $strText = str_replace('#lifetime#', (string) floor($this->tokenLifetime / 60), $strText);
                $email->text = $strText;

                // Send the email
                $email->sendTo($row['email']);

                // Add a log entry to Contao system log.
                $this->contaoGeneralLogger?->info(
                    sprintf('Password recovery link has been sent to backend user "%s" ID %d.', $row['username'], $row['id']),
                    ['contao' => new ContaoContext(__METHOD__, static::CONTAO_LOG_PW_RECOVERY_REQUEST)]
                );
            }

            // Redirect to the confirmation page
            $href = $this->router->generate(
                SendEmailConfirmController::ROUTE,
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            return $this->redirect($this->uriSigner->sign($href));
        }

        $objTemplate = new BackendTemplate('be_password_recovery_link_request');
        $objTemplate->showForm = true;
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
