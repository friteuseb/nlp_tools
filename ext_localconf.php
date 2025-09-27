<?php
defined('TYPO3') or die();

// Register extension configuration
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup('
    module.tx_nlptools.settings {
        cacheTtl = 3600
        maxTextLength = 10000
        defaultLanguage = en
    }
');
