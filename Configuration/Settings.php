<?php

return [
    'wortfreunde_connector' => [
        'webhook' => [
            'secret' => [
                'type' => 'string',
                'description' => 'Shared secret for HMAC webhook signature verification',
                'default' => '',
            ],
            'defaultPageUid' => [
                'type' => 'int',
                'description' => 'Default page UID where tt_content elements will be created',
                'default' => 0,
            ],
            'defaultLanguageUid' => [
                'type' => 'int',
                'description' => 'Default sys_language_uid for created content',
                'default' => 0,
            ],
            'defaultColPos' => [
                'type' => 'int',
                'description' => 'Default colPos for created tt_content elements',
                'default' => 0,
            ],
            'defaultContentType' => [
                'type' => 'string',
                'description' => 'CType for created tt_content elements (text or textmedia)',
                'default' => 'text',
            ],
            'enableLogging' => [
                'type' => 'bool',
                'description' => 'Enable webhook request logging',
                'default' => true,
            ],
        ],
    ],
];
