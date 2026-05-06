<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// Static TypoScript
// (muss in TYPO3 v13 in einem TCA Override liegen, nicht in ext_tables.php)
ExtensionManagementUtility::addStaticFile(
    'texteladen',
    'Configuration/TypoScript',
    'Texteladen'
);

