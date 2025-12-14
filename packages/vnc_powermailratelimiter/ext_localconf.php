<?php
defined('TYPO3') or die();

// Cache Konfiguration für den Rate Limiter

if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['vnc_ratelimit_cache'] ?? null)) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['vnc_ratelimit_cache'] = [
        'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
        'options' => [
            'defaultLifetime' => 0,
        ],
        'groups' => ['system'],
    ];
}
