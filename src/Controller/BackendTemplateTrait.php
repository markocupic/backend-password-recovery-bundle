<?php

declare(strict_types=1);

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2025 <m.cupic@gmx.ch>
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
        $objTemplate->theme = $framework->getAdapter(Backend::class)->getTheme();
        $objTemplate->messages = $framework->getAdapter(Message::class)->generate();
        $objTemplate->base = $framework->getAdapter(Environment::class)->get('base');
        $objTemplate->language = $framework->getAdapter(LocaleUtil::class)->formatAsLanguageTag($request->getLocale());
        $objTemplate->host = $framework->getAdapter(Backend::class)->getDecodedHostname();
        $objTemplate->charset = $framework->getAdapter(Config::class)->get('characterSet');
    }
}
