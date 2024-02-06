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

namespace Markocupic\BackendPasswordRecoveryBundle\Tests\ContaoManager;

use Code4Nix\UriSigner\UriSigner;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\DelegatingParser;
use Contao\TestCase\ContaoTestCase;
use Markocupic\BackendPasswordRecoveryBundle\ContaoManager\Plugin;
use Markocupic\BackendPasswordRecoveryBundle\MarkocupicBackendPasswordRecoveryBundle;

class PluginTest extends ContaoTestCase
{

    public function testInstantiation(): void
    {
        $this->assertInstanceOf(Plugin::class, new Plugin());
    }

    public function testGetBundles(): void
    {
        $plugin = new Plugin();

        /** @var array $bundles */
        $bundles = $plugin->getBundles(new DelegatingParser());

        $this->assertCount(2, $bundles);
        $this->assertInstanceOf(BundleConfig::class, $bundles[0]);
        $this->assertSame(UriSigner::class, $bundles[0]->getName());
        $this->assertInstanceOf(BundleConfig::class, $bundles[1]);
        $this->assertSame(MarkocupicBackendPasswordRecoveryBundle::class, $bundles[1]->getName());
        $this->assertSame([ContaoCoreBundle::class], $bundles[1]->getLoadAfter());
    }
}
