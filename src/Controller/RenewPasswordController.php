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
use Markocupic\BackendPasswordRecoveryBundle\Util\Crypt;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/backendpasswordrecovery/renewpassword/{token}', name: 'backend_password_recovery_renewpassword', defaults: ['_scope' => 'backend'])]
class RenewPasswordController extends AbstractController
{
    public const CONTAO_LOG_CAT = 'BACKEND_PASSWORD_RECOVERY';

    private Adapter $message;
    private Adapter $system;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly Crypt $crypt,
        private readonly InteractiveBackendLogin $interactiveBackendLogin,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoInfoLogger = null,
    ) {
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
        $this->initializeContaoFramework();

        // Check if token exists in the url
        if (empty($token)) {
            $this->message->addError($this->translator->trans('ERR.invalidPwRecoveryToken', [], 'contao_default'));

            return $this->redirectToRoute('contao_backend');
        }

        $valid = false;

        // Retrieve user from token.
        $rowUser = $this->connection->fetchAssociative(
            "SELECT * FROM tl_user WHERE activation LIKE '%--$token' AND disable = ? AND (start = ? OR start < ?) AND (stop = ? OR stop > ?) LIMIT 0,1",
            [
                '',
                '',
                time(),
                '',
                time(),
            ]
        );

        if ($rowUser) {
            $arrToken = explode('--', $rowUser['activation']);

            if (isset($arrToken[1])) {
                $iv = $arrToken[0]; // initialization vector
                $encryptedTstamp = $arrToken[1];
                $intExpiry = (int) $this->crypt->decrypt($encryptedTstamp, $iv);

                if ($intExpiry > time() && $intExpiry > 0) {
                    $valid = true;
                }
            }
        }

        if (!$valid) {
            $this->message->addError($this->translator->trans('ERR.invalidPwRecoveryToken', [], 'contao_default'));

            return $this->redirectToRoute('contao_backend');
        }

        $username = $rowUser['username'];

        // Interactive login
        if (!$this->interactiveBackendLogin->login($username)) {
            $this->message->addError($this->translator->trans('ERR.invalidPwRecoveryToken', [], 'contao_default'));

            return $this->redirectToRoute('contao_backend');
        }

        // Get the logged in backend user
        $user = $this->security->getUser();

        // Validate
        if (!$user instanceof BackendUser || $user->getUserIdentifier() !== $username) {
            $this->message->addError($this->translator->trans('ERR.invalidPwRecoveryToken', [], 'contao_default'));

            return $this->redirectToRoute('contao_backend');
        }

        // Trigger Contao post login Hook < Contao Version 5.0
        if (version_compare(ContaoCoreBundle::getVersion(), '5.0', 'lt')) {
            if (!empty($GLOBALS['TL_HOOKS']['postLogin']) && \is_array($GLOBALS['TL_HOOKS']['postLogin'])) {
                @trigger_error('Using the "postLogin" hook has been deprecated and will no longer work in Contao 5.0.', E_USER_DEPRECATED);

                foreach ($GLOBALS['TL_HOOKS']['postLogin'] as $callback) {
                    $this->system->importStatic($callback[0])->{$callback[1]}($user);
                }
            }
        }

        // Reset token, loginAttempts, etc.
        // and set pwChange to "1"
        // this is the way we can use the contao native "password forgot controller".
        $set = [
            'pwChange' => '1',
            'activation' => '',
            'loginAttempts' => 0,
            'locked' => 0,
        ];

        $this->connection->update('tl_user', $set, ['id' => (int) $user->id]);

        // Contao system log entry
        $this->contaoInfoLogger?->info(
            sprintf('Backend user "%s" has recovered his password.', $username),
            ['contao' => new ContaoContext(__METHOD__, static::CONTAO_LOG_CAT)]
        );

        // Redirect to the "contao_backend_password" route.
        return $this->redirectToRoute('contao_backend_password');
    }
}
