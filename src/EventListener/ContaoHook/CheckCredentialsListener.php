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

namespace Markocupic\BackendPasswordRecoveryBundle\EventListener\ContaoHook;

use Contao\BackendUser;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\User;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsHook(CheckCredentialsListener::HOOK)]
class CheckCredentialsListener
{
    public const HOOK = 'checkCredentials';

    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Contao < 5.0 (For Contao 5.x we use the LoginFailureListener).
     *
     * Do only show the password forgotten button
     * if the user entered the right username but a wrong password.
     */
    public function __invoke(string $username, string $credentials, User $user): bool
    {
        if ($user instanceof BackendUser) {
            $session = $this->requestStack->getCurrentRequest()->getSession();
            $session
                ->getFlashBag()
                ->set('loginAttemptHasValidUsernameButInvalidPassword', $username)
            ;
        }

        return false;
    }
}
