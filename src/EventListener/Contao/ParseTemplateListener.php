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

namespace Markocupic\BackendPasswordRecoveryBundle\EventListener\Contao;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Template;
use Markocupic\BackendPasswordRecoveryBundle\Controller\UserIdentifierFormController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsHook(ParseTemplateListener::HOOK)]
class ParseTemplateListener
{
    public const HOOK = 'parseTemplate';

    public function __construct(
        private readonly Environment $twig,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly bool $showButtonOnLoginFailureOnly,
    ) {
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function __invoke(Template $template): void
    {
        if (str_starts_with($template->getName(), 'be_login')) {
            $request = $this->requestStack->getCurrentRequest();

            // Skip listener if we have a cron request
            if (null === $request) {
                return;
            }

            $session = $request->getSession();
            $session->start();

            $displayPasswordResetLink = false;

            if (!empty($session->getFlashBag()->get('_show_password_recovery_link'))) {
                $displayPasswordResetLink = true;
            } elseif (!$this->showButtonOnLoginFailureOnly) {
                $displayPasswordResetLink = true;
            }

            if ($displayPasswordResetLink && $this->scopeMatcher->isBackendRequest($request)) {
                $locale = $request->getLocale();

                $href = $this->router->generate(UserIdentifierFormController::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);
                $href .= !empty($locale) ? '?_locale='.$locale : '';

                $template->messages .= $this->twig->render(
                    '@MarkocupicBackendPasswordRecovery/password_recovery_link.html.twig',
                    [
                        'href' => $this->uriSigner->sign($href),
                        'recoverPassword' => $this->translator->trans('MSC.recoverPassword', [], 'contao_default'),
                    ]
                );
            }
        }
    }
}
