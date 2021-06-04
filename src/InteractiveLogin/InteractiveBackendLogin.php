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

namespace Markocupic\BackendPasswordRecoveryBundle\InteractiveLogin;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Contao\CoreBundle\Security\User\UserChecker;
use Contao\System;
use Contao\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Class InteractiveLogin.
 */
class InteractiveBackendLogin
{
    /**
     * @var string provider key for contao backend secured area
     */
    public const SECURED_AREA_BACKEND = 'contao_backend';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var UserChecker
     */
    private $userChecker;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(ContaoFramework $framework, UserChecker $userChecker, Session $session, TokenStorageInterface $tokenStorage, EventDispatcherInterface $eventDispatcher, RequestStack $requestStack, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->userChecker = $userChecker;
        $this->session = $session;
        $this->tokenStorage = $tokenStorage;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
        $this->logger = $logger;

        $this->framework->initialize();
    }

    public function login(string $username): bool
    {
        $strFirewall = static::SECURED_AREA_BACKEND;

        $userClass = BackendUser::class;

        // Retrieve user by its username
        $userProvider = new ContaoUserProvider($this->framework, $this->session, $userClass, $this->logger);

        $user = $userProvider->loadUserByUsername($username);

        if (!$user) {
            return false;
        }

        $token = new UsernamePasswordToken($user, null, $strFirewall, $user->getRoles());
        $this->tokenStorage->setToken($token);

        // Save the token to the session
        $this->session->set('_security_'.$providerKey, serialize($token));
        $this->session->save();

        // Fire the login event manually
        $event = new InteractiveLoginEvent($this->requestStack->getCurrentRequest(), $token);
        $this->eventDispatcher->dispatch('security.interactive_login', $event);

        // Trigger the Contao post login hook
        $this->triggerPostLoginHook($user);

        return true;
    }

    /**
     * Trigger the Contao post login hook.
     */
    private function triggerPostLoginHook(User $user): void
    {
        if (empty($GLOBALS['TL_HOOKS']['postLogin']) || !\is_array($GLOBALS['TL_HOOKS']['postLogin'])) {
            return;
        }

        @trigger_error('Using the "postLogin" hook has been deprecated and will no longer work in Contao 5.0.', E_USER_DEPRECATED);

        /** @var System $system */
        $systemAdapter = $this->framework->getAdapter(System::class);

        foreach ($GLOBALS['TL_HOOKS']['postLogin'] as $callback) {
            $systemAdapter->importStatic($callback[0])->{$callback[1]}($user);
        }
    }
}
