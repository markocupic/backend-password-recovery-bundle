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
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @Hook("importUser")
 */
class ImportUser
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Do only show the password forgotten button
     * if the user entered the right username but a wrong password.
     */
    public function __invoke(string $username, string $password, string $table): bool
    {
        if ('tl_user' === $table) {
            $session = $this->requestStack->getCurrentRequest()->getSession();
            $session
                ->getFlashBag()
                ->set('invalidUsername', $username)
            ;
        }

        return false;
    }
}
