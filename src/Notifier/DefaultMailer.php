<?php

declare(strict_types=1);

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2025 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Notifier;

use Contao\Config;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Email;
use Contao\Environment;
use Contao\UserModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

class DefaultMailer extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'markocupic_backend_password_recovery.token_lifetime')]
        private readonly int $tokenLifetime,
    ) {
    }

    public function send(UserModel $user, string $strLink): bool
    {
        try {
            // Adapters
            $environment = $this->framework->getAdapter(Environment::class);
            $config = $this->framework->getAdapter(Config::class);

            // Send an email with a password recovery link to the user
            $email = new Email();
            $email->from = $GLOBALS['TL_ADMIN_EMAIL'] ?? $config->get('adminEmail');

            // Email: subject
            $subject = str_replace('#host#', $environment->get('base'), $this->translator->trans('MSC.pwRecoveryEmailSubject', [], 'contao_default'));
            $email->subject = $subject;

            // Email: text
            $body = str_replace('#host#', $environment->get('base'), $this->translator->trans('MSC.pwRecoveryEmailText', [], 'contao_default'));
            $body = str_replace('#link#', $strLink, $body);
            $body = str_replace('#lifetime#', (string) floor($this->tokenLifetime / 60), $body);

            // Add user props
            foreach ($user->row() as $k => $v) {
                $skip = [
                    'password',
                ];

                if (\in_array($k, $skip, true)) {
                    continue;
                }

                if (is_numeric($v) || \is_string($v)) {
                    if (false !== json_encode((string) $v)) {
                        $body = str_replace("#user_$k#", (string) $v, $body);
                    }
                }
            }

            $email->text = $body;

            // Send the email
            $success = $email->sendTo($user->email);

            if (!$success) {
                throw new \Exception('Something went wrong while trying to send the password recovery link.');
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
