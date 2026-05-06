<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// TypoScript-Setup registrieren.
ExtensionManagementUtility::addTypoScript(
    'texteladen',
    'setup',
    "@import 'EXT:texteladen/Configuration/TypoScript/setup.typoscript'",
    'defaultContentRendering'
);
