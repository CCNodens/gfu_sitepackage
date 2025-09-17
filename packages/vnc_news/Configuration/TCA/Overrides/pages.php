<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::registerPageTSConfigFile(
    'vncnews',
    'Configuration/TsConfig/Page/page.tsconfig',
    'EXT:vnc_news :: Enable images in News RTE'
);