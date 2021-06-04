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

namespace Markocupic\BackendPasswordRecoveryBundle;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\Database;
use Contao\Email;
use Contao\Environment;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class SendPasswordRecoveryLink
 */
class SendPasswordRecoveryLink extends Backend
{

	/**
	 * Initialize the controller
	 *
	 * 1. Import the user
	 * 2. Call the parent constructor
	 * 3. Authenticate the user
	 * 4. Load the language files
	 * DO NOT CHANGE THIS ORDER!
	 */
	public function __construct()
	{
		parent::__construct();

		$request = System::getContainer()->get('request_stack')->getCurrentRequest();

		if ($request->query->get('_locale') != '')
		{
			$request->setLocale($request->query->get('_locale'));
		}

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
		Message::reset();

		/** @var BackendTemplate|object $objTemplate */
		$objTemplate = new BackendTemplate('be_require_password_link');

		/** @var RouterInterface $router */
		$router = System::getContainer()->get('router');

		if ($request->request->get('FORM_SUBMIT') == 'tl_require_password_link' && $request->request->get('username') != '')
		{
			$username = $request->request->get('username');

			// Loading the importUser Hook
			if (!empty($GLOBALS['TL_HOOKS']['importUser']) && \is_array($GLOBALS['TL_HOOKS']['importUser']))
			{
				@trigger_error('Using the "importUser" hook has been deprecated and will no longer work in Contao 5.0. Use the contao.import_user event instead.', E_USER_DEPRECATED);

				foreach ($GLOBALS['TL_HOOKS']['importUser'] as $callback)
				{
					$objImport = System::importStatic($callback[0], 'objImport', true);
					$blnLoaded = $objImport->{$callback[1]}($username, 'nopassword', 'tl_user');

					if ($blnLoaded === true)
					{
						$username = $request->request->get('username');
						break;
					}
				}
			}

			$time = time();
			$objUser = Database::getInstance()->prepare("SELECT * FROM tl_user WHERE (email=? OR username=?) AND disable='' AND (start='' OR start<$time) AND (stop='' OR stop>$time)")->limit(1)->execute($username, $username);

			if ($objUser->numRows)
			{
				// Set renew password token
				$token = md5(uniqid(mt_rand(), true));

				// Write token to db
				Database::getInstance()->prepare("UPDATE tl_user SET activation=? WHERE id=?")->execute($token, $objUser->id);

				// Generate renew password link
				$strLink = Environment::get('url') . $router->generate('backend_password_recovery_renewpassword') . '?token=' . $token;

				// Send mail
				$objEmail = new Email();
				$objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];

				$objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['pwRecoveryText'][0], Environment::get('base'));
				$objEmail->text = sprintf($GLOBALS['TL_LANG']['MSC']['pwRecoveryText'][1], Environment::get('base'), $strLink);
				$objEmail->sendTo($objUser->email);
				System::log('Password for user ' . $objUser->username . ' has been reset.', __METHOD__, TL_GENERAL);
				Message::addConfirmation($GLOBALS['TL_LANG']['MSC']['pwRecoveryLinkSuccessfullySent']);
			}
			else
			{
				Message::addError($GLOBALS['TL_LANG']['ERR']['pwRecoveryFailed']);
			}
		}

		$objTemplate->theme = Backend::getTheme();
		$objTemplate->messages = Message::generate();
		$objTemplate->base = Environment::get('base');
		$objTemplate->language = $GLOBALS['TL_LANGUAGE'];
		$objTemplate->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pwRecoveryHeadline']);
		$objTemplate->charset = Config::get('characterSet');
		$objTemplate->action = ampersand(Environment::get('request'));
		$objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['pwRecoveryHeadline'];
		$objTemplate->usernameOrEmailPlaceholder = $GLOBALS['TL_LANG']['MSC']['usernameOrEmailPlaceholder'];
		$objTemplate->submitButton = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue']);
		$objTemplate->usernameOrEmail = $GLOBALS['TL_LANG']['MSC']['emailOrUsername'];
		$objTemplate->confirm = $GLOBALS['TL_LANG']['MSC']['confirm'][0];
		$objTemplate->feLink = $GLOBALS['TL_LANG']['MSC']['feLink'];

		return $objTemplate->getResponse();
	}
}
