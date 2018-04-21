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

use Contao\CoreBundle\Exception\AccessDeniedException;
use Patchwork\Utf8;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\System;
use Contao\BackendTemplate;
use Contao\Database;
use Contao\Message;
use Contao\UserModel;
use Contao\Backend;
use Contao\Environment;
use Contao\StringUtil;
use Contao\Config;

/**
 * Class BackendPassword
 * This class is more or less a clone from Contao\BackendPassword
 * @package Markocupic\BackendPasswordRecoveryBundle
 */
class BackendPassword extends Backend
{
    /**
     * @var \Contao\Database\Result|object
     */
    protected $User;


    /**
     * Initialize the controller
     *
     * 1. Call the parent constructor
     * 2. Authenticate the user
     * 3. Load the language files
     * DO NOT CHANGE THIS ORDER!
     */
    public function __construct()
    {
        parent::__construct();

        // Start session
        session_start();

        /** @var Request $request */
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        // Get the token from the request string
        $securityToken = $request->query->get('token');
        if ($securityToken == '')
        {
            throw new AccessDeniedException('No token found in the request stack.');
        }

        // Search the user that fits to the token
        $this->User = Database::getInstance()->prepare("SELECT * FROM tl_user WHERE activation=? AND disable=? AND (start=? OR start<?) AND (stop=? OR stop>?)")
            ->limit(1)
            ->execute(
                $securityToken,
                '',
                '',
                \time(),
                '',
                \time()
            );

        if (!$this->User->numRows)
        {
            throw new AccessDeniedException('User not found. Password recovery failed.');
        }

        if ($request->query->get('_locale') != '')
        {
            $request->setLocale($request->query->get('_locale'));
        }

        // Get language from the request query and load language file
        System::loadLanguageFile('default', $request->getLocale());
        System::loadLanguageFile('modules', $request->getLocale());

    }


    /**
     * Run the controller and parse the password template
     *
     * @return Response
     */
    public function run()
    {
        /** @var Request $request */
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        /** @var BackendTemplate|object $objTemplate */
        $objTemplate = new BackendTemplate('be_password');

        if ($request->request->get('FORM_SUBMIT') == 'tl_password')
        {
            $pw = $request->request->get('password');
            $cnf = $request->request->get('confirm');

            // The passwords do not match
            if ($pw != $cnf)
            {
                Message::addError($GLOBALS['TL_LANG']['ERR']['passwordMatch']);
            }
            // Password too short
            elseif (Utf8::strlen($pw) < \Config::get('minPasswordLength'))
            {
                Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['passwordLength'], \Config::get('minPasswordLength')));
            }
            // Password and username are the same
            elseif ($pw == $this->User->username)
            {
                Message::addError($GLOBALS['TL_LANG']['ERR']['passwordName']);
            }
            // Save the data
            else
            {
                // Make sure the password has been changed
                if (password_verify($pw, $this->User->password))
                {
                    Message::addError($GLOBALS['TL_LANG']['MSC']['pw_change']);
                }
                else
                {
                    $this->loadDataContainer('tl_user');

                    // Trigger the save_callback
                    if (\is_array($GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback']))
                    {
                        foreach ($GLOBALS['TL_DCA']['tl_user']['fields']['password']['save_callback'] as $callback)
                        {
                            if (\is_array($callback))
                            {
                                $this->import($callback[0]);
                                $pw = $this->{$callback[0]}->{$callback[1]}($pw);
                            }
                            elseif (\is_callable($callback))
                            {
                                $pw = $callback($pw);
                            }
                        }
                    }

                    $objUser = UserModel::findByPk($this->User->id);
                    $objUser->pwChange = '';
                    $objUser->password = password_hash($pw, PASSWORD_DEFAULT);

                    // Unlock account
                    $objUser->activation = '';
                    $objUser->loginCount = 3;
                    $objUser->locked = 0;

                    $objUser->save();

                    Message::addConfirmation($GLOBALS['TL_LANG']['MSC']['pw_changed']);
                    $_SESSION['pw_recovery']['status'] = 'success';
                    $this->redirect('contao/login');
                }
            }

        }

        $objTemplate->theme = Backend::getTheme();
        $objTemplate->messages = Message::generate();
        $objTemplate->base = Environment::get('base');
        $objTemplate->language = $GLOBALS['TL_LANGUAGE'];
        $objTemplate->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pw_new']);
        $objTemplate->charset = Config::get('characterSet');
        $objTemplate->action = ampersand(\Environment::get('request'));
        $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['pw_new'];
        $objTemplate->explain = $GLOBALS['TL_LANG']['MSC']['pw_change'];
        $objTemplate->submitButton = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue']);
        $objTemplate->password = $GLOBALS['TL_LANG']['MSC']['password'][0];
        $objTemplate->confirm = $GLOBALS['TL_LANG']['MSC']['confirm'][0];

        return $objTemplate->getResponse();
    }
}
