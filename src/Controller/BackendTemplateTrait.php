<?php

declare(strict_types=1);

/*
 * This file is part of "Backend Password Recovery Bundle".
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;

use Contao\Backend;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Util\LocaleUtil;
use Contao\Message;
use Symfony\Component\HttpFoundation\Request;

trait BackendTemplateTrait
{
    /**
     * Returns the variables of the "be_password_recovery_layout" template.
     */
    protected function getLayoutData(Request $request, ContaoFramework $framework): array
    {
        $backend = $framework->getAdapter(Backend::class);

        return [
            // Backend::getTheme() has been removed in Contao 6 (there is only one back end theme)
            'theme' => method_exists(Backend::class, 'getTheme') ? $backend->getTheme() : null,
            'messages' => $framework->getAdapter(Message::class)->generate(),
            'language' => $framework->getAdapter(LocaleUtil::class)->formatAsLanguageTag($request->getLocale()),
            'host' => $backend->getDecodedHostname(),
            'charset' => $framework->getAdapter(Config::class)->get('characterSet') ?: 'UTF-8',
        ];
    }
}
