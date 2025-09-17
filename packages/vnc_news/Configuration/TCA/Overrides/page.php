<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::registerPageTSConfigFile(
    'vncnews',
    'Configuration/TsConfig/Page/LinkBrowser/NewsImageLinkHandler.tsconfig',
    'EXT:vnc_news :: Enable images in News RTE'
);