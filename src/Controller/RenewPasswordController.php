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

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Markocupic\BackendPasswordRecoveryBundle\InteractiveLogin\InteractiveBackendLogin;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class RenewPasswordController.
 *
 * @Route(defaults={"_scope" = "backend"})
 */
class RenewPasswordController extends AbstractController
{
    const CONTAO_LOG_CAT = 'BACKEND_PASSWORD_RECOVERY';
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var InteractiveBackendLogin
     */
    private $interactiveBackendLogin;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection, RouterInterface $router, InteractiveBackendLogin $interactiveBackendLogin, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->router = $router;
        $this->interactiveBackendLogin = $interactiveBackendLogin;
        $this->logger = $logger;
    }

    /**
     * @Route("/backendpasswordrecovery/renewpassword", name="backend_password_recovery_renewpassword")
     */
    public function renewpasswordAction(): Response
    {
        $this->initializeContaoFramework();

        $request = $this->requestStack->getCurrentRequest();

        // Get the token from the request string.
        $securityToken = $request->query->get('token');

        if (!$request || empty($securityToken)) {
            throw new AccessDeniedException('Access denied due to invalid request or missing token.');
        }

        // Get user from token.
        $stmt = $this->connection->prepare('SELECT * FROM tl_user WHERE activation=? AND disable=? AND (start=? OR start<?) AND (stop=? OR stop>?) LIMIT 0,1');
        $stmt->bindValue(1, $securityToken);
        $stmt->bindValue(2, '');
        $stmt->bindValue(3, '');
        $stmt->bindValue(4, time());
        $stmt->bindValue(5, '');
        $stmt->bindValue(6, time());
        $stmt->execute();

        if (!($arrUsers = $stmt->fetchAll())) {
            return new Response('Backend user not found. Perhaps your token has expired. Please try to restore your password again.', Response::HTTP_FORBIDDEN);
        }

        $arrUser = $arrUsers[0];

        // Interactive login
        if ($this->interactiveBackendLogin->login($arrUser['username'])) {
            /** @var QueryBuilder $qb */
            $qb = $this->connection->createQueryBuilder();

            // Reset token, loginAttempts, etc.
            // and set pwChange to "1"
            // thats the way we can use contao native password forgot controller.
            $qb->update('tl_user', 'u')
                ->set('u.pwChange', ':pwChange', \PDO::PARAM_STR)
                ->set('u.activation', ':activation', \PDO::PARAM_STR)
                ->set('u.loginAttempts', ':loginAttempts', \PDO::PARAM_INT)
                ->set('u.locked', ':locked', \PDO::PARAM_INT)
                ->where('u.id = :id')
                ->setParameter('pwChange', '1')
                ->setParameter('activation', '')
                ->setParameter('loginAttempts', 0)
                ->setParameter('locked', 0)
                ->setParameter('id', (int) $arrUser['id'], \PDO::PARAM_INT)
            ;

            $qb->execute();

            // Log
            if ($this->logger) {
                $strText = sprintf('Backend user "%s" has recovered his password.', $arrUser['username']);
                $this->logger->log(
                    LogLevel::INFO,
                    $strText,
                    ['contao' => new ContaoContext(__METHOD__, static::CONTAO_LOG_CAT)]
                );
            }

            // Redirects to the "contao_backend_password" route.
            return $this->redirectToRoute('contao_backend_password');
        }

        return new Response('Backend user not found. Perhaps your token has expired. Please try to restore your password again.', Response::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS);
    }
}
