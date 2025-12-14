<?php
$EM_CONF['vnc_powermailratelimiter'] = [
    'title' => 'vnc-powermailratelimiter',
    'description' => 'Implements a PSR-15 middleware to protect powermail forms against high-frequency spam submissions by rate limiting requests based on IP address.',
    'category' => 'plugin',
    'author' => 'Vancado',
    'author_email' => 'michael.pick@vancado.de',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99'
        ],
    ],
];