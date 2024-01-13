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
use Contao\Config;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Email;
use Contao\Environment;
use Contao\Message;
use Contao\System;
use Contao\UserModel;
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
    use BackendTemplateTrait;

    public const ROUTE = 'backend_password_recovery.user_identifier_form';
    public const CONTAO_LOG_PW_RECOVERY_REQUEST = 'BE_PW_RECOVERY_REQUEST';

    public function __construct(
        private readonly ContaoFramework $framework,
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

        $messageAdapter = $this->framework->getAdapter(Message::class);
        $systemAdapter = $this->framework->getAdapter(System::class);
        $userAdapter = $this->framework->getAdapter(UserModel::class);
        $uuidAdapter = $this->framework->getAdapter(Uuid::class);

        if (!$this->uriSigner->checkRequest($request)) {
            return new Response('Access denied!', Response::HTTP_FORBIDDEN);
        }

        $systemAdapter->loadLanguageFile('default');
        $systemAdapter->loadLanguageFile('modules');

        if ('tl_require_password_link_form' === $request->request->get('FORM_SUBMIT') && '' !== $request->request->get('usernameOrEmail')) {
            $usernameOrEmail = $request->request->get('usernameOrEmail');
            $now = time();
            $t = $userAdapter->getTable();
            $where = ["($t.email LIKE ? OR $t.username = ?) AND $t.disable = '' AND ($t.start = '' OR $t.start < ?) AND ($t.stop = '' OR $t.stop > ?)"];

            $user = $userAdapter->findOneBy($where, [$usernameOrEmail, $usernameOrEmail, $now, $now]);

            if (null !== $user) {
                $token = $uuidAdapter->uuid4()->toString();

                // Save token and token lifetime to the user entity
                $user->pwResetToken = $token;
                $user->pwResetLifetime = time() + $this->tokenLifetime; // Default 600 (10 min)
                $user->save();

                // Generate password recovery link
                $strLink = $this->router->generate(TokenAuthenticationController::ROUTE, ['_token' => base64_encode($token)], UrlGeneratorInterface::ABSOLUTE_URL);

                // Redirect back to the login form on error
                if (!$this->sendEmail($user, $strLink)) {
                    $messageAdapter->addError($this->translator->trans('ERR.unexpectedAuth', [], 'contao_default'));
                    $href = $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL);

                    return $this->redirect($href);
                }

                // Add a log entry to Contao system log
                $this->contaoGeneralLogger?->info(
                    sprintf('Password recovery link has been sent to backend user "%s" ID %d.', $user->username, $user->id),
                    ['contao' => new ContaoContext(__METHOD__, static::CONTAO_LOG_PW_RECOVERY_REQUEST)]
                );
            }

            // Redirect to the confirmation page
            $href = $this->router->generate(SendEmailConfirmController::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);

            return $this->redirect($this->uriSigner->sign($href));
        }

        $objTemplate = new BackendTemplate('be_password_recovery_form');
        $this->addMoreDataToTemplate($objTemplate, $request, $this->framework);

        return $objTemplate->getResponse();
    }

    private function sendEmail(UserModel $user, string $strLink): bool
    {
        try {
            // Adapters
            $environmentAdapter = $this->framework->getAdapter(Environment::class);
            $configAdapter = $this->framework->getAdapter(Config::class);

            // Send email with password recovery link to the user
            $email = new Email();
            $email->from = $GLOBALS['TL_ADMIN_EMAIL'] ?? $configAdapter->get('adminEmail');

            // Email: subject
            $strSubject = str_replace('#host#', $environmentAdapter->get('base'), $this->translator->trans('MSC.pwRecoveryEmailSubject', [], 'contao_default'));
            $email->subject = $strSubject;

            // Email: text
            $strText = str_replace('#host#', $environmentAdapter->get('base'), $this->translator->trans('MSC.pwRecoveryEmailText', [], 'contao_default'));
            $strText = str_replace('#link#', $strLink, $strText);
            $strText = str_replace('#lifetime#', (string) floor($this->tokenLifetime / 60), $strText);

            // Add user props
            foreach ($user->row() as $k => $v) {
                $skip = [
                    'password',
                ];

                if (\in_array($k, $skip, true)) {
                    continue;
                }

                if (is_numeric($v) || \is_string($v)) {
                    if (false !== json_encode((string) $v)) {
                        $strText = str_replace("#user_$k#", (string) $v, $strText);
                    }
                }
            }

            $email->text = $strText;

            // Send the email
            $emailSuccess = $email->sendTo($user->email);

            if (!$emailSuccess) {
                throw new \Exception('Something went wrong while trying to send the password recovery link.');
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
