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
use Contao\Template;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Class ParseTemplate.
 */
class ParseTemplate
{
    private $framework;

    private $requestStack;

    private $twig;

    private $translator;

    private $session;

    public function __construct(ContaoFramework $framework, $requestStack, Environment $twig, TranslatorInterface $translator, SessionInterface $session)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->twig = $twig;
        $this->translator = $translator;
        $this->session = $session;
    }

    /**
     * @param $objTemplate
     */
    public function addPwRecoveryLinkToBackendLoginForm(Template $objTemplate): void
    {
        if (TL_MODE === 'BE') {
            if (0 === strpos($objTemplate->getName(), 'be_login')) {

              // Show reset password link if login has failed
                if (false !== strpos($objTemplate->messages, substr($this->translator->trans('ERR.invalidLogin', [], 'contao_default'), 0, 10)) || false !== strpos($objTemplate->messages, substr($this->translator->trans('ERR.accountLocked', [], 'contao_default'), 0, 10))) {
                    $locale = $this->requestStack->getMasterRequest()->getLocale();
                    $href = sprintf('backendpasswordrecovery/requirepasswordrecoverylink?_locale=%s', $locale);

                    $objTemplate->messages .= $this->twig->render(
                      '@MarkocupicBackendPasswordRecovery/password_recovery_button.html.twig',
                      [
                          'password_recovery_button' => sprintf($this->translator->trans('ERR.invalidBackendLogin', [], 'contao_default'), $href),
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
                          'confirmation_text' => $this->translator->trans('MSC.pwrecoverySuccess', [], 'contao_default'),
                        ]
                      );
                    }
                }
            }
        }
    }
}
