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
 * Hooks
 */
if (TL_MODE == 'BE')
{
    // Inject password recovery link to default login form, if the login process fails.
    $GLOBALS['TL_HOOKS']['parseTemplate'][] = array('Markocupic\BackendPasswordRecoveryBundle\ParseTemplateHook', 'addPwRecoveryLinkToBackendLoginForm');

    // Load the backend stylesheet
    $GLOBALS['TL_CSS'][] = 'bundles/markocupicbackendpasswordrecovery/stylesheet.css|static';

}

