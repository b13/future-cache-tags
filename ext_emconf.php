<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Future Cache Tags',
    'description' => 'Flushes page caches the moment scheduled records go live or expire: remembers the cache tags of every record with a future start or end time and flushes them on time, via CLI worker or Symfony Messenger. For pages, content, news and any other TCA table, workspace-aware.',
    'category' => 'misc',
    'author' => 'b13 GmbH',
    'author_email' => 'typo3@b13.com',
    'author_company' => 'b13 GmbH',
    'state' => 'beta',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-15.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'workspaces' => '13.4.0-15.99.99',
            'scheduler' => '13.4.0-15.99.99',
        ],
    ],
];
