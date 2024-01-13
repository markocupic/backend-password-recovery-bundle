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

namespace Markocupic\BackendPasswordRecoveryBundle\Security\Authenticator;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Message;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\BackendPasswordRecoveryBundle\Controller\TokenAuthenticationController;
use Markocupic\BackendPasswordRecoveryBundle\Security\Authenticator\Exception\UserNotFoundAuthenticationException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Translation\TranslatorInterface;

class Authenticator extends AbstractAuthenticator
{
    public const CONTAO_LOG_PW_RECOVERY_SUCCESS = 'BE_PW_RECOVERY_SUCCESS';
    public const CONTAO_LOG_PW_RECOVERY_FAILURE = 'BE_PW_RECOVERY_FAILURE';

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    public function supports(Request $request): bool
    {
        if (!$this->scopeMatcher->isBackendRequest($request)) {
            return false;
        }

        if (TokenAuthenticationController::ROUTE !== $request->attributes->get('_route')) {
            return false;
        }

        if (!$request->attributes->has('_token')) {
            return false;
        }

        // Do nothing if Contao backend user is already logged in
        $user = $this->security->getUser();

        if ($user instanceof BackendUser) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $this->framework->initialize();

        // Get the message adapter
        $message = $this->framework->getAdapter(Message::class);

        $token = $request->attributes->get('_token');

        try {
            $token = base64_decode((string) $token, true);

            $now = time();

            // Retrieve user from token.
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM tl_user WHERE pwResetToken = ? AND pwResetLifetime > ? AND disable = ? AND (start = ? OR start < ?) AND (stop = ? OR stop > ?)',
                [
                    $token,
                    $now,
                    '',
                    '',
                    $now,
                    '',
                    $now,
                ]
            );

            if (false === $row) {
                throw new UserNotFoundAuthenticationException('Could not retrieve Contao user from password recovery token.');
            }

            $username = $row['username'];
        } catch (UserNotFoundAuthenticationException $e) {
            $message->addError($this->translator->trans('ERR.'.$e->getMessageKey(), [], 'contao_default'));
            $log = sprintf('Could not retrieve Contao user from token "%s".', $token);

            throw new AuthenticationException($log);
        } catch (\Exception $e) {
            $message->addError($this->translator->trans('ERR.unexpectedAuth', [], 'contao_default'));
            $log = 'Something went wrong while trying to recover the password.';

            throw new AuthenticationException($log);
        }

        return new SelfValidatingPassport(new UserBadge($username));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $firewallName): Response|null
    {
        $username = $token->getUserIdentifier();

        $adapter = $this->framework->getAdapter(UserModel::class);
        $t = $adapter->getTable();

        $user = $adapter->findBy([$t.'.username = ?'], [$username]);

        // Reset pwResetToken, pwResetLifetime, etc.
        // and set pwChange to '1'
        // this is the way we can use the "ContaoBackend" controller from the Contao core.
        $user->pwResetToken = '';
        $user->pwResetLifetime = 0;
        $user->pwChange = '1';
        $user->save();

        // Add a log entry to Contao system log.
        $this->contaoGeneralLogger?->info(
            sprintf('Backend user "%s" has recovered his password.', $username),
            ['contao' => new ContaoContext(__METHOD__, static::CONTAO_LOG_PW_RECOVERY_SUCCESS)]
        );

        $url = $this->router->generate('contao_backend_password', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return new RedirectResponse($url);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response|null
    {
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
