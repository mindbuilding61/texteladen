<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// Add new CType
ExtensionManagementUtility::addTcaSelectItem(
    'tt_content',
    'CType',
    [
        'label' => 'LLL:EXT:texteladen/Resources/Private/Language/locallang_db.xlf:tt_content.CType.texteladen_text',
        'value' => 'texteladen_text',
        'icon' => 'content-texteladen',
        'group' => 'common',
        'description' => 'LLL:EXT:texteladen/Resources/Private/Language/locallang_db.xlf:tt_content.CType.texteladen_text.description',
    ],
    'text',
    'after'
);

// New fields
$newColumns = [
    'tx_texteladen_folder' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:texteladen/Resources/Private/Language/locallang_db.xlf:tt_content.tx_texteladen_folder',
        'config' => [
            'type' => 'folder',
            'maxitems' => 1,
            'elementBrowserEntryPoints' => [
                // fileadmin (Default storage is usually UID 1)
                '_default' => '1:/',
            ],
        ],
    ],
    'tx_texteladen_tag' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:texteladen/Resources/Private/Language/locallang_db.xlf:tt_content.tx_texteladen_tag',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim',
            'placeholder' => 'news',
        ],
    ],
    'tx_texteladen_words_per_page' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:texteladen/Resources/Private/Language/locallang_db.xlf:tt_content.tx_texteladen_words_per_page',
        'config' => [
            'type' => 'number',
            'size' => 8,
            'default' => 200,
            'range' => [
                'lower' => 10,
                'upper' => 5000,
            ],
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('tt_content', $newColumns);

// Show fields in new CType
$GLOBALS['TCA']['tt_content']['types']['texteladen_text'] = [
    'showitem' => '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            --palette--;;headers,
        --div--;LLL:EXT:texteladen/Resources/Private/Language/locallang_db.xlf:tabs.settings,
            tx_texteladen_folder,
            tx_texteladen_tag,
            tx_texteladen_words_per_page,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:appearance,
            --palette--;;frames,
            --palette--;;appearanceLinks,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,
            categories,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes,
            rowDescription,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended
    ',
];

