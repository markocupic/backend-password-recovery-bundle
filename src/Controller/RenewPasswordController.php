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

use Contao\BackendUser;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Message;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\BackendPasswordRecoveryBundle\InteractiveLogin\InteractiveBackendLogin;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/backendpasswordrecovery/renewpassword/{token}', name: 'backend_password_recovery_renewpassword', defaults: ['_scope' => 'backend'])]
class RenewPasswordController extends AbstractController
{
    public const CONTAO_LOG_PW_RECOVERY_SUCCESS = 'BE_PW_RECOVERY_SUCCESS';

    private Adapter $contaoCoreBundle;
    private Adapter $message;
    private Adapter $system;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly InteractiveBackendLogin $interactiveBackendLogin,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        $this->contaoCoreBundle = $this->framework->getAdapter(ContaoCoreBundle::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->system = $this->framework->getAdapter(System::class);
    }

    /**
     * 1. Get Contao backend user from token
     * 2. Interactive login
     * 3. Set tl_user.pwChange to '1'
     * 4. Redirect to Contao native "password forgot controller".
     *
     * @throws Exception
     */
    public function __invoke($token = null): Response
    {
        $token = base64_decode((string) $token, true);

        $this->initializeContaoFramework();

        // Check if token exists in the url
        if (empty($token)) {
            $this->message->addError($this->translator->trans('ERR.invalidPwRecoveryToken', [], 'contao_default'));

            return $this->redirectToRoute('contao_backend');
        }

        $isValid = false;

        $now = time();

        // Retrieve user from token.
        $rowUser = $this->connection->fetchAssociative(
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

        if ($rowUser) {
            $isValid = true;
        }

        if (!$isValid) {
            if (!$this->hasLoggedInBackendUser()) {
                $this->message->addError($this->translator->trans('ERR.invalidPwRecoveryToken', [], 'contao_default'));
            }

            return $this->redirectToRoute('contao_backend');
        }

        $username = $rowUser['username'];

        // Interactive login
        if (!$this->interactiveBackendLogin->login($username)) {
            $this->message->addError($this->translator->trans('ERR.invalidPwRecoveryToken', [], 'contao_default'));

            return $this->redirectToRoute('contao_backend');
        }

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        // Trigger Contao post login Hook < Contao Version 5.0
        if (version_compare($this->contaoCoreBundle->getVersion(), '5.0', 'lt')) {
            if (!empty($GLOBALS['TL_HOOKS']['postLogin']) && \is_array($GLOBALS['TL_HOOKS']['postLogin'])) {
                @trigger_error('Using the "postLogin" hook has been deprecated and will no longer work in Contao 5.0.', E_USER_DEPRECATED);

                foreach ($GLOBALS['TL_HOOKS']['postLogin'] as $callback) {
                    $this->system->importStatic($callback[0])->{$callback[1]}($user);
                }
            }
        }

        // Reset pwResetToken, pwResetLifetime, etc.
        // and set pwChange to '1'
        // this is the way we can use the contao native "ContaoBackend" controller.
        $set = [
            'pwResetToken' => '',
            'pwResetLifetime' => 0,
            'pwChange' => '1',
        ];

        $this->connection->update('tl_user', $set, ['id' => (int) $user->id]);

        // Add a log entry to Contao system log.
        $this->contaoGeneralLogger?->info(
            sprintf('Backend user "%s" has recovered his password.', $username),
            ['contao' => new ContaoContext(__METHOD__, static::CONTAO_LOG_PW_RECOVERY_SUCCESS)]
        );

        // Redirect to the 'contao_backend_password' route.
        return $this->redirectToRoute('contao_backend_password');
    }

    private function hasLoggedInBackendUser()
    {
        $user = $this->security->getUser();

        return $user instanceof BackendUser;
    }
}
