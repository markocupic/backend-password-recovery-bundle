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

namespace Markocupic\BackendPasswordRecoveryBundle\Tests\DependencyInjection;

use Markocupic\BackendPasswordRecoveryBundle\Controller\UserIdentifierFormController;
use Markocupic\BackendPasswordRecoveryBundle\DependencyInjection\MarkocupicBackendPasswordRecoveryExtension;
use Markocupic\BackendPasswordRecoveryBundle\NotificationType\BackendPasswordRecoveryNotificationType;
use Markocupic\BackendPasswordRecoveryBundle\Notifier\DefaultMailer;
use Markocupic\BackendPasswordRecoveryBundle\Notifier\NC;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Terminal42\NotificationCenterBundle\NotificationCenter;

class MarkocupicBackendPasswordRecoveryExtensionTest extends TestCase
{
    public function testLoadsTheServicesAndParameters(): void
    {
        $container = $this->loadExtension(['token_lifetime' => 900]);

        $this->assertTrue($container->hasDefinition(UserIdentifierFormController::class));
        $this->assertTrue($container->hasDefinition(DefaultMailer::class));
        $this->assertSame(900, $container->getParameter('markocupic_backend_password_recovery.token_lifetime'));
        $this->assertTrue($container->getParameter('markocupic_backend_password_recovery.show_password_recovery_link_on_login_failure_only'));
    }

    public function testRegistersTheNotificationCenterServicesOnlyIfItIsInstalled(): void
    {
        $container = $this->loadExtension([]);
        $installed = class_exists(NotificationCenter::class);

        $this->assertSame($installed, $container->hasDefinition(NC::class));
        $this->assertSame($installed, $container->hasDefinition(BackendPasswordRecoveryNotificationType::class));
    }

    private function loadExtension(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();

        (new MarkocupicBackendPasswordRecoveryExtension())->load([$config], $container);

        return $container;
    }
}
