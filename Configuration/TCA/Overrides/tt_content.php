<?php

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'Skills',
    'Skills',
    'Skills-Skills'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['skills_skills'] = 'layout,pages,recursive';

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'Skills',
    'Users',
    'Skills-Users'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['skills_users'] = 'layout,pages,recursive';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['skills_users'] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue('skills_users', 'FILE:EXT:skills/Configuration/FlexForms/flexform_users.xml');

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'Skills',
    'ShortLink',
    'Skills-Short links'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['skills_shortlink'] = 'layout,pages,recursive';

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'Skills',
    'Routing',
    'Skills-Routing'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['skills_routing'] = 'layout,pages,recursive';

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'Skills',
    'Organisations',
    'Skills-Organisations'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['skills_organisations'] = 'layout,pages,recursive';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['skills_organisations'] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue('skills_organisations', 'FILE:EXT:skills/Configuration/FlexForms/flexform_organisations.xml');

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'Skills',
    'Api',
    'Skills-Api'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['skills_api'] = 'layout,pages,recursive';

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'Skills',
    'Anonymous',
    'Skills-Anonymous-Login'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['skills_anonymous_login'] = 'layout,pages,recursive';
