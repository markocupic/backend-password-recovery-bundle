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

/*
 * Errors
 */
$GLOBALS['TL_LANG']['ERR']['contaoUserNotFoundOrLinkHasExpiredAuth'] = 'Password reset failed. User not found or password recovery link has expired.';
$GLOBALS['TL_LANG']['ERR']['unexpectedAuth'] = 'An unexpected error occurred while trying to recover the password.';

/*
 * Miscellaneous
 */
$GLOBALS['TL_LANG']['MSC']['recoverPassword'] = 'Go to password recovery';
$GLOBALS['TL_LANG']['MSC']['pwRecoveryHeadline'] = 'Password recovery';
$GLOBALS['TL_LANG']['MSC']['usernameOrEmailPlaceholder'] = 'Email or your username';
$GLOBALS['TL_LANG']['MSC']['usernameOrEmailExplain'] = 'Please enter your email address or username to receive an email message with the password recovery link.';
$GLOBALS['TL_LANG']['MSC']['forgotPassword'] = 'forgot password';
$GLOBALS['TL_LANG']['MSC']['pwRecoveryLinkSuccessfullySent'] = 'If a user with the user details you entered exists, you will shortly receive an e-mail with instructions on how to recover your password. Please also check your spam folder if the message is not in your inbox.';

/*
 * Email
 */
$GLOBALS['TL_LANG']['MSC']['pwRecoveryEmailSubject'] = 'Your password request on #host#';
$GLOBALS['TL_LANG']['MSC']['pwRecoveryEmailText'] = '
Hi #user_name#

You have requested a new password for #host#.

Please open the link below to set up your new password. Please note that the link is only valid for #lifetime# minutes.

#link#

If you did not request this email, please contact the website administrator.



---------------------------------

This is an auto-generated message. Do not answer please.
';
