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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\BackendPasswordRecoveryBundle\InteractiveLogin\InteractiveBackendLogin;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

#[Route('/backendpasswordrecovery/renewpassword/{token}', name: 'backend_password_recovery_renewpassword', defaults: ['_scope' => 'backend'])]
class RenewPasswordController extends AbstractController
{
    public const CONTAO_LOG_CAT = 'BACKEND_PASSWORD_RECOVERY';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly InteractiveBackendLogin $interactiveBackendLogin,
        private readonly Security $security,
        private readonly LoggerInterface|null $logger = null,
    ) {
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

        // Check if token exists in the url -> empty('0') === true
        if (empty($token)) {
            return new Response('Access denied due to missing or invalid token.', Response::HTTP_UNAUTHORIZED);
        }

        // Get user from token.
        $result = $this->connection->executeQuery(
            'SELECT * FROM tl_user WHERE activation = ? AND disable = ? AND (start = ? OR start < ?) AND (stop = ? OR stop > ?) LIMIT 0,1',
            [
                $token,
                '',
                '',
                time(),
                '',
                time(),
            ]
        );

        $strErrorMsg = 'Backend user not found. Perhaps your token has expired. Please try to restore your password again.';

        if (false === ($arrUsers = $result->fetchAssociative())) {
            return new Response($strErrorMsg, Response::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS);
        }

        $username = $arrUsers['username'];

        // Interactive login
        if (!$this->interactiveBackendLogin->login($username)) {
            return new Response($strErrorMsg, Response::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS);
        }

        // Get logged in backend user
        $user = $this->security->getUser();

        // Validate
        if (!$user instanceof BackendUser || $user->getUserIdentifier() !== $username) {
            return new Response($strErrorMsg, Response::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS);
        }

        // Trigger Contao post login Hook
        if (version_compare(ContaoCoreBundle::getVersion(), '5.0', 'lt')) {
            if (!empty($GLOBALS['TL_HOOKS']['postLogin']) && \is_array($GLOBALS['TL_HOOKS']['postLogin'])) {
                @trigger_error('Using the "postLogin" hook has been deprecated and will no longer work in Contao 5.0.', E_USER_DEPRECATED);

                /** @var System $systemAdapter */
                $systemAdapter = $this->framework->getAdapter(System::class);

                foreach ($GLOBALS['TL_HOOKS']['postLogin'] as $callback) {
                    $systemAdapter->importStatic($callback[0])->{$callback[1]}($user);
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
        if ($this->logger) {
            $strText = sprintf('Backend user "%s" has recovered his password.', $username);
            $this->logger->log(
                LogLevel::INFO,
                $strText,
                ['contao' => new ContaoContext(__METHOD__, static::CONTAO_LOG_CAT)]
            );
        }

        // Redirect to the "contao_backend_password" route.
        return $this->redirectToRoute('contao_backend_password');
    }
}
