<?php

declare(strict_types=1);

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\Database;
use Contao\Email;
use Contao\Environment;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class PasswordRecoveryLinkRequestController.
 *
 * @Route(defaults={"_scope" = "backend", "_token_check" = true})
 */
class PasswordRecoveryLinkRequestController extends AbstractController
{
    /**
     * @var UriSigner
     */
    private $uriSigner;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var CsrfTokenManagerInterface
     */
    private $tokenManager;

    /**
     * @var string
     */
    private $csrfTokenName;

    public function __construct(UriSigner $uriSigner, RequestStack $requestStack, RouterInterface $router, CsrfTokenManagerInterface $tokenManager, string $csrfTokenName)
    {
        $this->uriSigner = $uriSigner;
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->tokenManager = $tokenManager;
        $this->csrfTokenName = $csrfTokenName;
    }

    /**
     * @Route("/backendpasswordrecovery/requirepasswordrecoverylink", name="backend_password_recovery_requirepasswordrecoverylink")
     */
    public function requirepasswordrecoverylinkAction(): Response
    {
        $this->initializeContaoFramework();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->uriSigner->check($request->getUri())) {
            return new Response('Access denied!', Response::HTTP_FORBIDDEN);
        }

        System::loadLanguageFile('default');
        System::loadLanguageFile('modules');

        if ('tl_require_password_link_form' === $request->request->get('FORM_SUBMIT') && '' !== $request->request->get('usernameOrEmail')) {
            $usernameOrEmail = $request->request->get('usernameOrEmail');
            $time = time();

            $objUser = Database::getInstance()
                ->prepare("SELECT * FROM tl_user WHERE (email LIKE ? OR username=?) AND disable='' AND (start='' OR start<$time) AND (stop='' OR stop>$time)")
                ->limit(1)
                ->execute($usernameOrEmail, $usernameOrEmail)
            ;

            if (!$objUser->numRows) {
                Message::addError($GLOBALS['TL_LANG']['ERR']['pwRecoveryFailed']);
            } else {
                // Set renew password token
                $token = md5(uniqid((string) mt_rand(), true));

                // Write token to db
                Database::getInstance()
                    ->prepare('UPDATE tl_user SET activation=? WHERE id=?')
                    ->execute($token, $objUser->id)
                ;

                // Generate renew password link
                $strLink = Environment::get('url').$this->router->generate('backend_password_recovery_renewpassword').'?token='.$token;

                // Send email
                $objEmail = new Email();
                $objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];

                // Subject
                $strSubject = str_replace('#host#', Environment::get('base'), $GLOBALS['TL_LANG']['MSC']['pwRecoveryEmailSubject']);
                $objEmail->subject = $strSubject;

                // Text
                $strText = str_replace('#host#', Environment::get('base'), $GLOBALS['TL_LANG']['MSC']['pwRecoveryEmailText']);
                $strText = str_replace('#link#', $strLink, $strText);
                $strText = str_replace('#name#', $objUser->name, $strText);

                $objEmail->text = $strText;

                // Send message
                $objEmail->sendTo($objUser->email);

                // Show message in the backend
                Message::addConfirmation($GLOBALS['TL_LANG']['MSC']['pwRecoveryLinkSuccessfullySent']);
            }
        }

        $objTemplate = new BackendTemplate('be_password_recovery_link_request');
        $objTemplate->requestToken = $this->tokenManager->getToken($this->csrfTokenName)->getValue();
        $objTemplate->theme = Backend::getTheme();
        $objTemplate->messages = Message::generate();
        $objTemplate->base = Environment::get('base');
        $objTemplate->language = $GLOBALS['TL_LANGUAGE'];
        $objTemplate->title = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['pwRecoveryHeadline']);
        $objTemplate->host = Backend::getDecodedHostname();
        $objTemplate->charset = Config::get('characterSet');
        $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['pwRecoveryHeadline'];
        $objTemplate->usernameOrEmailPlaceholder = $GLOBALS['TL_LANG']['MSC']['usernameOrEmailPlaceholder'];
        $objTemplate->usernameOrEmailExplain = $GLOBALS['TL_LANG']['MSC']['usernameOrEmailExplain'];
        $objTemplate->submitButton = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['continue']);

        return $objTemplate->getResponse();
    }
}
