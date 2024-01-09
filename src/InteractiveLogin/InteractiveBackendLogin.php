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

namespace Markocupic\BackendPasswordRecoveryBundle\InteractiveLogin;

use Contao\BackendUser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class InteractiveBackendLogin
{
    public const SECURED_AREA_BACKEND = 'contao_backend';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function login(string $username): bool
    {
        $this->framework->initialize();
        $session = $this->requestStack->getCurrentRequest()->getSession();

        // Retrieve user by its username
        if (version_compare(ContaoCoreBundle::getVersion(), '5.0', 'lt')) {
            // Contao 4.x
            $userProvider = new ContaoUserProvider($this->framework, $session, BackendUser::class, $this->logger);
            $user = $userProvider->loadUserByIdentifier($username);
            $authenticatedToken = new UsernamePasswordToken($user, null, static::SECURED_AREA_BACKEND, $user->getRoles());
        } else {
            // Contao 5.x
            $userProvider = new ContaoUserProvider($this->framework, BackendUser::class);
            $user = $userProvider->loadUserByIdentifier($username);
            $authenticatedToken = new UsernamePasswordToken($user, static::SECURED_AREA_BACKEND, $user->getRoles());
        }

        if (!is_a($authenticatedToken, UsernamePasswordToken::class)) {
            return false;
        }

        $this->tokenStorage->setToken($authenticatedToken);

        // Save the token to the session
        $session->set('_security_'.self::SECURED_AREA_BACKEND, serialize($authenticatedToken));
        $session->save();

        $event = new InteractiveLoginEvent($this->requestStack->getCurrentRequest(), $authenticatedToken);
        $this->eventDispatcher->dispatch($event, 'security.interactive_login');

        if (!is_a($event, InteractiveLoginEvent::class)) {
            return false;
        }

        $user = $authenticatedToken->getUser();

        if ($user instanceof BackendUser) {
            if ($username === $user->username) {
                return true;
            }
        }

        return false;
    }
}
