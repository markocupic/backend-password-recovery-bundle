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

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Util\LocaleUtil;
use Contao\Environment;
use Contao\Message;
use Symfony\Component\HttpFoundation\Request;

trait BackendTemplateTrait
{
    protected function addMoreDataToTemplate(BackendTemplate $objTemplate, Request $request, ContaoFramework $framework): void
    {
        $environmentAdapter = $framework->getAdapter(Environment::class);
        $configAdapter = $framework->getAdapter(Config::class);
        $messageAdapter = $framework->getAdapter(Message::class);
        $localUtilAdapter = $framework->getAdapter(LocaleUtil::class);
        $backendAdapter = $framework->getAdapter(Backend::class);

        $objTemplate->theme = $backendAdapter->getTheme();
        $objTemplate->messages = $messageAdapter->generate();
        $objTemplate->base = $environmentAdapter->get('base');
        $objTemplate->language = $localUtilAdapter->formatAsLanguageTag($request->getLocale());
        $objTemplate->host = $backendAdapter->getDecodedHostname();
        $objTemplate->charset = $configAdapter->get('characterSet');
    }
}
