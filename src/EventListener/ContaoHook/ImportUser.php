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

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsHook(ImportUser::HOOK)]
class ImportUser
{
    public const HOOK = 'importUser';

    private RequestStack $requestStack;

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
        @trigger_error('Using the "importUser" hook has been deprecated and will no longer work in Contao 5.0.', E_USER_DEPRECATED);

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
