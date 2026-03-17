<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Wortfreunde Connector',
    'description' => 'Receives Markdown blog posts from wortfreunde.ch via webhooks and converts them to tt_content elements.',
    'category' => 'plugin',
    'author' => 'dkd Internet Service GmbH',
    'author_email' => 'opensource@dkd.de',
    'author_company' => 'dkd Internet Service GmbH',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
            'php' => '8.1.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
