<?php

return [
    'wortfreunde' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/web/wortfreunde',
        'iconIdentifier' => 'wortfreunde-module',
        'labels' => 'LLL:EXT:wortfreunde_connector/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => \Wortfreunde\WortfreundeConnector\Controller\WebhookLogController::class . '::listAction',
            ],
        ],
    ],
];
