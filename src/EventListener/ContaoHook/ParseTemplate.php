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

namespace Markocupic\BackendPasswordRecoveryBundle\EventListener\ContaoHook;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use Markocupic\BackendPasswordRecoveryBundle\Controller\UserIdentifierFormController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsHook(ParseTemplate::HOOK)]
class ParseTemplate
{
    public const HOOK = 'parseTemplate';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly bool $showButtonOnLoginFailureOnly,
    ) {
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(Template $objTemplate): void
    {
        if (str_starts_with($objTemplate->getName(), 'be_login')) {
            /** @var Request $request */
            $request = $this->requestStack->getCurrentRequest();

            // Skip listener if we have a cron request
            if (null === $request) {
                return;
            }

            $session = $request->getSession();
            $session->start();

            $displayRenewPasswordLink = false;

            if (!empty($session->getFlashBag()->get('_show_password_recovery_link'))) {
                $displayRenewPasswordLink = true;
            } elseif (!$this->showButtonOnLoginFailureOnly) {
                $displayRenewPasswordLink = true;
            }

            if ($displayRenewPasswordLink && $this->scopeMatcher->isBackendRequest($request)) {
                // Generate password recover link
                $locale = $request->getLocale();

                $href = sprintf(
                    $this->router->generate(
                        UserIdentifierFormController::ROUTE,
                        [],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ).'?_locale=%s',
                    $locale
                );

                $signedUri = $this->uriSigner->sign($href);
                $objTemplate->recoverPasswordLink = $signedUri;

                // Forgot password label
                $objTemplate->forgotPassword = $this->translator->trans('MSC.forgotPassword', [], 'contao_default');

                // Show reset password link if login has failed
                $objTemplate->messages .= $this->twig->render(
                    '@MarkocupicBackendPasswordRecovery/password_recovery_link.html.twig',
                    [
                        'href' => $signedUri,
                        'recoverPassword' => $this->translator->trans('MSC.recoverPassword', [], 'contao_default'),
                    ]
                );
            }
        }
    }
}
