<?php

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

/**
 * Hooks
 */
if (TL_MODE === 'BE')
{
	// Load the backend stylesheet
	$GLOBALS['TL_CSS'][] = 'bundles/markocupicbackendpasswordrecovery/stylesheet.css|static';
}
