<?php

defined('TYPO3') || die('Access denied.');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
    'gfu_sitepackage',
    'Configuration/TypoScript/Static',
    'GfU Sitepackage Setup'
);
