<?php

/**
 * Backend Password Reoovery Bundle for Contao CMS
 *
 * Copyright (C) 2005-2018 Marko Cupic
 *
 * @package Backend Password Recovery Bundle
 * @link    https://www.github.com/markocupic/backend-password-recovery-bundle
 *
 */


/**
 * Errors
 */
$GLOBALS['TL_LANG']['ERR']['pwrecoveryFailed'] = 'No user found with this username or email address.';
$GLOBALS['TL_LANG']['ERR']['invalidBackendLogin'] = '<div class="tl_pw_recovery"><p>Go to<br><a class="tl_submit password_recovery_button" href="backendpasswordrecovery/requirepasswordrecoverylink?_locale=%s">password recovery?</a></p></div>';


/**
 * Miscellaneous
 */
$GLOBALS['TL_LANG']['MSC']['pwrecoveryHeadline'] = 'Password recovery';
$GLOBALS['TL_LANG']['MSC']['usernameOrEmailPlaceholder'] = 'Email or your username';
$GLOBALS['TL_LANG']['MSC']['emailOrUsername'] = 'Please enter your email address or your username.';
$GLOBALS['TL_LANG']['MSC']['newPassword'] = 'Please enter your new password';
$GLOBALS['TL_LANG']['MSC']['beLogin'] = 'Contao back end login';
$GLOBALS['TL_LANG']['MSC']['pwrecovery'] = 'Reset password';
$GLOBALS['TL_LANG']['MSC']['recoverBT'] = 'Reset password';
$GLOBALS['TL_LANG']['MSC']['pwrecoveryLinkSuccessfullySent'] = 'We\'ve sent you an email explaining how to reset your password.';
$GLOBALS['TL_LANG']['MSC']['pwrecoverySuccess'] = 'You have successfully restored your password. Please try to login now.';
$GLOBALS['TL_LANG']['MSC']['pwrecoveryText'] = array('Your password request on %s', "You have requested a new password for %s.\n\nPlease click %s to set the new password. If you did not request this e-mail, please contact the website administrator.\n\n\n\n---------------------------------\n\nThis is an auto-generated message. Do not answer please.");

