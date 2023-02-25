<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;

return static function (ECSConfig $ecsConfig): void {

    $services = $ecsConfig->services();
    $services
        ->set(HeaderCommentFixer::class)
        ->call('configure', [
            [
                'header' => "This file is part of Backend Password Recovery Bundle.\n\n(c) Marko Cupic ".date('Y')." <m.cupic@gmx.ch>\n@license MIT\nFor the full copyright and license information,\nplease view the LICENSE file that was distributed with this source code.\n@link https://github.com/markocupic/backend-password-recovery-bundle",
            ],
        ]);

    $ecsConfig->skip([
        MethodChainingIndentationFixer::class => [
            'DependencyInjection/Configuration.php',
        ],
    ]);

    $ecsConfig->parallel();
    $ecsConfig->lineEnding("\n");
};
