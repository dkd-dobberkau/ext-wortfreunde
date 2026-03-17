<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:wortfreunde_connector/Resources/Private/Language/locallang_db.xlf:tx_wortfreundeconnector_webhook_log',
        'label' => 'webhook_id',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'crdate DESC',
        'iconfile' => 'EXT:wortfreunde_connector/Resources/Public/Icons/webhook-log.svg',
        'rootLevel' => -1,
        'searchFields' => 'webhook_id,event_type,delivery_id,status',
    ],
    'types' => [
        '0' => [
            'showitem' => 'webhook_id, event_type, delivery_id, status, payload, error_message, tt_content_uid, page_uid',
        ],
    ],
    'columns' => [
        'webhook_id' => [
            'label' => 'Webhook ID',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'readOnly' => true,
            ],
        ],
        'event_type' => [
            'label' => 'Event Type',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'delivery_id' => [
            'label' => 'Delivery ID',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'readOnly' => true,
            ],
        ],
        'payload' => [
            'label' => 'Payload',
            'config' => [
                'type' => 'text',
                'rows' => 15,
                'readOnly' => true,
            ],
        ],
        'status' => [
            'label' => 'Status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'Pending', 'value' => 'pending'],
                    ['label' => 'Processed', 'value' => 'processed'],
                    ['label' => 'Error', 'value' => 'error'],
                ],
                'readOnly' => true,
            ],
        ],
        'error_message' => [
            'label' => 'Error Message',
            'config' => [
                'type' => 'text',
                'rows' => 5,
                'readOnly' => true,
            ],
        ],
        'tt_content_uid' => [
            'label' => 'Created tt_content UID',
            'config' => [
                'type' => 'input',
                'eval' => 'int',
                'readOnly' => true,
            ],
        ],
        'page_uid' => [
            'label' => 'Target Page UID',
            'config' => [
                'type' => 'input',
                'eval' => 'int',
                'readOnly' => true,
            ],
        ],
    ],
];
