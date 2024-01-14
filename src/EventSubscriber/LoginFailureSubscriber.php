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

namespace Markocupic\BackendPasswordRecoveryBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

class LoginFailureSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    /**
     * Replaces the importUser Hook,
     * that has been removed in Contao 5.
     *
     * https://github.com/symfony/symfony/blob/7.0/src/Symfony/Component/Security/Http/Event/LoginFailureEvent.php
     */
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $passport = $event->getPassport();

        if (null === $passport) {
            return;
        }

        $session = $event->getRequest()->getSession();
        $session->start();
        $session
            ->getFlashBag()
            ->set('_show_password_recovery_link', 'true')
        ;
    }
}
