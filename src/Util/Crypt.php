<?php

declare(strict_types=1);

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Util;

use Contao\System;

class Crypt
{
    private const CIPHERING = 'AES-128-CTR';

    public function getInitializationVector(): string
    {
        return md5(uniqid());
    }

    public function encrypt(string $string, string $initializationVector, $options = 0): string
    {
        return openssl_encrypt(
            $string,
            self::CIPHERING,
            $this->getEncryptionKey(),
            $options,
            $initializationVector,
        );
    }

    public function decrypt(string $string, string $initializationVector, $options = 0): string
    {
        return openssl_decrypt(
            $string,
            self::CIPHERING,
            $this->getEncryptionKey(),
            $options,
            $initializationVector,
        );
    }

    private function getEncryptionKey(): string
    {
        if (isset($_SERVER['APP_SECRET'])) {
            return $_SERVER['APP_SECRET'];
        }

        $container = System::getContainer();

        if ($container->hasParameter('secret')) {
            return $container->getParameter('secret');
        }

        throw new \LogicException('Secret not found! Please check your configuration.');
    }
}
