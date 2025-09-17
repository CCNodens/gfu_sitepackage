<?php
$EM_CONF['gfu_sitepackage'] = [
    'title' => 'gfu_sitepackage',
    'description' => 'Simple sitepackage for TYPO3 v13',
    'category' => 'plugin',
    'author' => 'GFU',
    'author_email' => '',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99'
        ],
    ],
];