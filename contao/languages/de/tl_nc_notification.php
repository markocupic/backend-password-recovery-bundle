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

use Markocupic\BackendPasswordRecoveryBundle\NotificationType\BackendPasswordRecoveryNotificationType;

$GLOBALS['TL_LANG']['tl_nc_notification']['type'][BackendPasswordRecoveryNotificationType::NAME] = ['Backed-User: Passwort-Wiederherstellung'];
