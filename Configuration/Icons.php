<?php

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'wortfreunde-module' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:wortfreunde_connector/Resources/Public/Icons/Extension.svg',
    ],
    'wortfreunde-webhook-log' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:wortfreunde_connector/Resources/Public/Icons/webhook-log.svg',
    ],
];
