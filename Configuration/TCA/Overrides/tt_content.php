<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

$pluginConfigNew = [
    ['Organisations', true],
    ['Skills', false],
    ['UserRegister', false],
    ['UserEdit', false],
    ['ShortLink', false],
    ['Routing', false],
    ['Api', false],
    ['Anonymous', false],
];

foreach ($pluginConfigNew as $pluginConfigItem) {
    $pluginNameUpperCamelCase = $pluginConfigItem[0];
    $pluginNameSnakeCase = GeneralUtility::camelCaseToLowerCaseUnderscored($pluginNameUpperCamelCase);
    $pluginNameDashCase = 'skills-' . str_replace('_', '-', $pluginNameSnakeCase);
    $pluginNameNoCase = str_replace('_', '', $pluginNameSnakeCase);
    $contentTypeName = 'skills_' . $pluginNameNoCase;

    ExtensionUtility::registerPlugin(
        'Skills',
        $pluginNameUpperCamelCase,
        'LLL:EXT:skills/Resources/Private/Language/backend.xlf:plugin.skills_' . $pluginNameSnakeCase . '.title',
        $pluginNameDashCase,
        'skills'
    );

    if ($pluginConfigItem[1] ?? false) {
        ExtensionManagementUtility::addPiFlexFormValue(
            '*',
            'FILE:EXT:skills/Configuration/FlexForms/flexform_' . $pluginNameSnakeCase . '.xml',
            $contentTypeName
        );

        $GLOBALS['TCA']['tt_content']['types'][$contentTypeName]['showitem'] = '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            --palette--;;headers,
        --div--;LLL:EXT:skills/Resources/Private/Language/backend.xlf:plugin.tab,
            pi_flexform,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
            --palette--;;language,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,';
    } else {
        $GLOBALS['TCA']['tt_content']['types'][$contentTypeName]['showitem'] = '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            --palette--;;headers,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
            --palette--;;language,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,';
    }

    $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'][$contentTypeName] = $pluginNameDashCase;
}
