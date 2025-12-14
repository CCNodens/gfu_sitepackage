<?php
return [
    'frontend' => [
        'vancado/vncpowermail-rate-limiter' => [
            'target' => \Vancado\VncPowermailratelimiter\Middleware\PowermailRateLimiter::class,
            'after' => ['typo3/cms-frontend/site'],
        ],
    ],
];