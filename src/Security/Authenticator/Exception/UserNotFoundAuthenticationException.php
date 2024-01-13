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

namespace Markocupic\BackendPasswordRecoveryBundle\Security\Authenticator\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class UserNotFoundAuthenticationException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'contaoUserNotFoundAuth';
    }
}
