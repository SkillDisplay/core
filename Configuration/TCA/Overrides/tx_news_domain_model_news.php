<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$fields = [
    'brand' => [
        'exclude' => 1,
        'label' => 'Organisation',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'foreign_table' => 'tx_skills_domain_model_brand',
            'foreign_table_where' => 'AND tx_skills_domain_model_brand.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_brand.name',
            'items' => [
                ['', 0],
            ],
            'default' => 0,
        ],
    ],
];

if (isset($GLOBALS['TCA']['tx_news_domain_model_news'])) {
    ExtensionManagementUtility::addTCAcolumns('tx_news_domain_model_news', $fields);
    ExtensionManagementUtility::addToAllTCAtypes('tx_news_domain_model_news', 'brand');
}
