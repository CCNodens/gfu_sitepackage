<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use Vancado\VncNews\LinkHandler\NewsImageLinkHandling;

$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['vncnews'] = 'EXT:vnc_news/Configuration/RTE/News.yaml';

$GLOBALS['TYPO3_CONF_VARS']['SYS']['linkHandler']['newsimage'] = NewsImageLinkHandling::class;