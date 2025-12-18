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

namespace Markocupic\BackendPasswordRecoveryBundle\Notifier;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\StringUtil;
use Contao\UserModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Terminal42\NotificationCenterBundle\NotificationCenter;

class NC extends AbstractController
{
    public function __construct(
        private readonly NotificationCenter $notificationCenter,
        #[Autowire(param: 'markocupic_backend_password_recovery.token_lifetime')]
        private readonly int $tokenLifetime,
    ) {
    }

    public function send(UserModel $user, string $strLink, array $notificationIds): bool
    {
        $tokens = [
            'link' => $strLink,
            'token_lifetime' => (string) floor($this->tokenLifetime / 60),
        ];

        foreach ($user->row() as $k => $v) {
            $skip = [
                'password',
            ];

            if (\in_array($k, $skip, true)) {
                continue;
            }

            $tokens['user_'.$k] = \is_string($v) ? StringUtil::revertInputEncoding($v) : (string) $v;
        }

        $count = 0;

        try {
            foreach ($notificationIds as $notificationId) {
                $receiptCollection = $this->notificationCenter->sendNotification($notificationId, $tokens, !empty($user->language) ? $user->language : null);
                $count += $receiptCollection->count();
            }

            if (0 === $count) {
                throw new \Exception('Could not send the notification via Notification Center.');
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
