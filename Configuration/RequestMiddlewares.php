<?php

return [
    'frontend' => [
        'wortfreunde/wortfreunde-webhook' => [
            'target' => \Wortfreunde\WortfreundeConnector\Middleware\WebhookMiddleware::class,
            'before' => [
                'typo3/cms-frontend/site',
            ],
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
        ],
    ],
];
