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

namespace Markocupic\BackendPasswordRecoveryBundle\Listener\ContaoHooks;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Class ParseTemplate.
 */
class ParseTemplate
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var UriSigner
     */
    private $uriSigner;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Environment $twig, TranslatorInterface $translator, SessionInterface $session, UriSigner $uriSigner, RouterInterface $router, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->twig = $twig;
        $this->translator = $translator;
        $this->session = $session;
        $this->uriSigner = $uriSigner;
        $this->router = $router;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * @param $objTemplate
     */
    public function addPasswordRecoveryLinkToContaoBackendLoginForm(Template $objTemplate): void
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        if ($this->scopeMatcher->isBackendRequest($request)) {
            if (0 === strpos($objTemplate->getName(), 'be_login')) {
                // Generate password recover link
                $locale = $request->getLocale();

                $href = sprintf(
                    $request->getSchemeAndHttpHost().$this->router->generate('backend_password_recovery_requirepasswordrecoverylink').'?_locale=%s',
                    $locale
                );

                $signedUri = $this->uriSigner->sign($href);
                $objTemplate->recoverPasswordLink = $signedUri;

                // Forgot password label
                $objTemplate->forgotPassword = $this->translator->trans('MSC.forgotPassword', [], 'contao_default');

                // Show reset password link if login has failed
                if (false !== strpos($objTemplate->messages, substr($this->translator->trans('ERR.invalidLogin', [], 'contao_default'), 0, 10)) || false !== strpos($objTemplate->messages, substr($this->translator->trans('ERR.accountLocked', [], 'contao_default'), 0, 10))) {
                    $objTemplate->messages .= $this->twig->render(
                        '@MarkocupicBackendPasswordRecovery/password_recovery_button.html.twig',
                        [
                            'password_recovery_button' => sprintf(
                              $this->translator->trans('ERR.invalidBackendLogin', [], 'contao_default'),
                              $signedUri
                            ),
                        ]
                    );
                }

                if ($this->session->has('pw_recovery')) {
                    $arrBag = $this->session->get('pw_recovery');

                    if (\is_array($arrBag) && isset($arrBag['status']) && 'success' === $arrBag['status']) {
                        $this->session->remove('pw_recovery');

                        $objTemplate->messages .= $this->twig->render(
                        '@MarkocupicBackendPasswordRecovery/password_recovery_confirmation.html.twig',
                        [
                            'confirmation_text' => $this->translator->trans('MSC.pwRecoverySuccess', [], 'contao_default'),
                        ]
                      );
                    }
                }
            }
        }
    }
}
