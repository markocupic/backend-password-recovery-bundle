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

$GLOBALS['TL_DCA']['tl_user']['fields']['pwResetToken'] = [
    'sql' => "varchar(256) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_user']['fields']['pwResetLifetime'] = [
    'sql' => 'int(10) unsigned NOT NULL default 0',
];
