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


namespace Markocupic\BackendPasswordRecoveryBundle;

use Contao\Controller;
use Contao\System;

/**
 * Class ParseTemplateHook
 * @package Markocupic\BackendPasswordRecoveryBundle
 */
class ParseTemplateHook
{

    /**
     * ParseTemplateHook
     * @param $objTemplate
     */
    public function addPwRecoveryLinkToBackendLoginForm($objTemplate)
    {

        if (TL_MODE === 'BE')
        {
            if ($objTemplate->getName() === 'be_login')
            {
                // Start session
                session_start();

                $request = System::getContainer()->get('request_stack')->getCurrentRequest();
                $locale = $request->getLocale();
                $envPath = Controller::replaceInsertTags('{{env::path}}');
                $href = sprintf('%sbackendpasswordrecovery/requirepasswordrecoverylink?_locale=%s', $envPath, $locale);
                $url = sprintf($GLOBALS['TL_LANG']['ERR']['invalidBackendLogin'], $href);

                // Show reset password link if login has failed
                if (strpos($objTemplate->messages, substr($GLOBALS['TL_LANG']['ERR']['invalidLogin'], 0, 10)) !== false || strpos($objTemplate->messages, substr($GLOBALS['TL_LANG']['ERR']['accountLocked'], 0, 10)) !== false)
                {
                    $objTemplate->messages = $objTemplate->messages . '<div class="tl_message"><p class="tl_error">' . $url . '</p></div>';
                }

                if ($_SESSION['pw_recovery']['status'] === 'success')
                {
                    unset($_SESSION['pw_recovery']);
                    $objTemplate->messages = $objTemplate->messages . '<div class="tl_message"><p class="tl_confirm">' . $GLOBALS['TL_LANG']['MSC']['pwrecoverySuccess'] . '</p></div>';
                }
            }
        }
    }
}
