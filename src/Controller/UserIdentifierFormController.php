<?php

declare(strict_types=1);

/*
 * This file is part of "Backend Password Recovery Bundle".
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;

use Code4Nix\UriSigner\UriSigner;
use Contao\BackendTemplate;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Message;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\BackendPasswordRecoveryBundle\NotificationType\BackendPasswordRecoveryNotificationType;
use Markocupic\BackendPasswordRecoveryBundle\Notifier\DefaultMailer;
use Markocupic\BackendPasswordRecoveryBundle\Notifier\NC;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\NotificationCenterBundle\NotificationCenter;

#[Route('/_backend_password_recovery/form', name: self::ROUTE, defaults: ['_scope' => 'backend', '_token_check' => true])]
class UserIdentifierFormController extends AbstractController
{
    use BackendTemplateTrait;

    public const ROUTE = 'backend_password_recovery.user_identifier_form';

    public const CONTAO_LOG_PW_RECOVERY_REQUEST = 'BE_PW_RECOVERY_REQUEST';

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly DefaultMailer $defaultMailer,
        private readonly NC $nc,
        private readonly NotificationCenter $notificationCenter,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        #[Autowire(param: 'markocupic_backend_password_recovery.token_lifetime')]
        private readonly int $tokenLifetime,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->initializeContaoFramework();

        if (!$this->uriSigner->checkRequest($request)) {
            return new Response('Access denied!', Response::HTTP_FORBIDDEN);
        }

        $this->loadRequiredLanguageFiles(['default', 'modules']);

        $identifier = $request->request->get('usernameOrEmail');

        if ('tl_require_password_link_form' === $request->request->get('FORM_SUBMIT') && !empty($identifier)) {
            return $this->handleFormSubmit($identifier);
        }

        $template = new BackendTemplate('be_password_recovery_form');
        $template->backHref = $this->router->generate('contao_backend');
        $this->addMoreDataToTemplate($template, $request, $this->framework);

        return $template->getResponse();
    }

    private function handleFormSubmit(string $identifier): RedirectResponse
    {
        $user = $this->findUserByIdentifier($identifier);

        if (null !== $user) {
            $token = Uuid::uuid4()->toString();

            // Save token and token lifetime to the user entity.
            $user->pwResetToken = $token;
            $user->pwResetLifetime = time() + $this->tokenLifetime; // Default 600 (10 min)
            $user->save();

            $link = $this->generateLink($token);
            $notificationIds = $this->getNotificationIds();

            if (!empty($notificationIds)) {
                $success = $this->nc->send($user, $link, $notificationIds);
            } else {
                $success = $this->defaultMailer->send($user, $link);
            }

            if (!$success) {
                $this->getContaoAdapter(Message::class)->addError($this->translator->trans('ERR.unexpectedAuth', [], 'contao_default'));

                // Redirect the user to the backend login page.
                $href = $this->router->generate('contao_backend', [], UrlGeneratorInterface::ABSOLUTE_URL);

                return $this->redirect($href);
            }

            // Add a message to the Contao system log.
            $this->contaoGeneralLogger?->info(
                \sprintf('Password recovery link has been sent to backend user "%s" ID %d.', $user->username, $user->id),
                ['contao' => new ContaoContext(__METHOD__, static::CONTAO_LOG_PW_RECOVERY_REQUEST)],
            );
        }

        // Redirect the user to the confirmation page.
        $href = $this->router->generate(SendEmailConfirmController::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->redirect($this->uriSigner->sign($href, $this->tokenLifetime));
    }

    private function loadRequiredLanguageFiles(array $langFiles): void
    {
        foreach ($langFiles as $langFile) {
            $this->getContaoAdapter(System::class)->loadLanguageFile($langFile);
        }
    }

    private function generateLink(string $token): string
    {
        $link = $this->router->generate(ResetVerifyController::ROUTE, ['_token' => base64_encode($token)], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($link, $this->tokenLifetime);
    }

    private function getNotificationIds(): array
    {
        $type = BackendPasswordRecoveryNotificationType::NAME;

        return $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_nc_notification WHERE type = ?',
            [$type],
            [Types::STRING],
        );
    }

    private function findUserByIdentifier(string $identifier): UserModel|null
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('tl_user', 't')
            ->where('t.email LIKE :identifier OR t.username = :identifier')
            ->andWhere('t.disable = 0')
            ->andWhere('t.start = "" OR t.start < :now')
            ->andWhere('t.stop = "" OR t.stop > :now')
            ->setParameters([
                'identifier' => $identifier,
                'now' => time(),
            ])
        ;

        $id = $qb->fetchOne();

        if (false === $id) {
            return null;
        }

        return $this->getContaoAdapter(UserModel::class)->findById($id);
    }
}
