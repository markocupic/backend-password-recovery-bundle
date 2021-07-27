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

namespace Markocupic\BackendPasswordRecoveryBundle\Listener\ContaoHooks;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @Hook("importUser")
 */
class ImportUser
{
    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * Do only show the password forgotten button
     * if the user entered the right username but a wroong password.
     * @param string $username
     * @param string $password
     * @param string $table
     * @return bool
     */
    public function __invoke(string $username, string $password, string $table): bool
    {
        if ('tl_user' === $table) {
            $this->session
                ->getFlashBag()
                ->set('invalidUsername', $username)
            ;
        }

        return false;
    }
}
