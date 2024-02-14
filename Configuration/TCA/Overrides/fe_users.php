<?php

use SkillDisplay\Skills\Hook\SkillsProcFunc;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

ExtensionManagementUtility::addToAllTCAtypes(
    'fe_users',
    $GLOBALS['TCA']['fe_users']['ctrl']['type'],
    '',
    'after:' . $GLOBALS['TCA']['fe_users']['ctrl']['label']
);

$tmp_skills_columns = [
    'publish_skills' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.publish_skills',
        'config' => [
            'type' => 'check',
            'items' => [
                1 => [
                    0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                ],
            ],
            'default' => 0,
        ],
    ],
    'locked' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.locked',
        'config' => [
            'type' => 'check',
            'items' => [
                1 => [
                    0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                ],
            ],
            'default' => 0,
        ],
    ],
    'avatar' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.avatar',
        'config' => ExtensionManagementUtility::getFileFieldTCAConfig(
            'avatar',
            [
                'appearance' => [
                    'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:images.addFileReference',
                ],
                'foreign_match_fields' => [
                    'fieldname' => 'avatar',
                    'tablenames' => 'fe_users',
                    'table_local' => 'sys_file',
                ],
                'overrideChildTca' => [
                    'types' => [
                        0 => [
                            'showitem' => '
                        --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                        --palette--;;filePalette',
                        ],
                        File::FILETYPE_IMAGE => [
                            'showitem' => '
                        --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                        --palette--;;filePalette',
                        ],
                    ],
                ],
                'maxitems' => 1,
            ],
            $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
        ),
    ],
    'favourite_certifiers' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.favourite_certifiers',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectMultipleSideBySide',
            'foreign_table' => 'tx_skills_domain_model_certifier',
            'MM' => 'tx_skills_user_certifier_mm',
            'size' => 3,
            'autoSizeMax' => 10,
            'maxitems' => 9999,
            'multiple' => 0,
        ],
    ],
    'managed_brands' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.managed_brands',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectMultipleSideBySide',
            'foreign_table' => 'tx_skills_domain_model_brand',
            'foreign_table_where' => 'AND tx_skills_domain_model_brand.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_brand.name',
            'MM' => 'tx_skills_user_brand_mm',
            'size' => 3,
            'autoSizeMax' => 10,
            'maxitems' => 9999,
            'multiple' => 0,
            'itemsProcFunc' => SkillsProcFunc::class . '->filterBrandsForUser',
        ],
    ],
    'organisations' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.organisations',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectMultipleSideBySide',
            'foreign_table' => 'tx_skills_domain_model_brand',
            'foreign_table_where' => 'AND tx_skills_domain_model_brand.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_brand.name',
            'MM' => 'tx_skills_user_organisation_mm',
            'size' => 3,
            'autoSizeMax' => 10,
            'maxitems' => 9999,
            'multiple' => 0,
            'itemsProcFunc' => SkillsProcFunc::class . '->filterBrandsForUser',
        ],
    ],
    'mail_push' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.mail_push',
        'config' => [
            'type' => 'check',
            'items' => [
                1 => [
                    0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                ],
            ],
            'default' => 1,
        ],
    ],
    'mail_language' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.mail_language',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['en', 'en'],
                ['de', 'de'],
            ],
            'default' => 'en',
        ],
    ],
    'newsletter' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.newsletter',
        'config' => [
            'type' => 'check',
            'items' => [
                1 => [
                    0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                ],
            ],
            'default' => 0,
        ],
    ],
    'xing' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.xing',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 100,
            'eval' => 'trim',
        ],
    ],
    'linkedin' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.linkedin',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 100,
            'eval' => 'trim',
        ],
    ],
    'github' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.github',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 100,
            'eval' => 'trim',
        ],
    ],
    'twitter' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.twitter',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 100,
            'eval' => 'trim',
        ],
    ],
    'pending_email' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.pending_email',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'max' => 100,
            'eval' => 'trim',
        ],
    ],
    'profile_link' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.profile_link',
        'config' => [
            'type' => 'input',
            'renderType' => 'inputLink',
            'size' => 30,
            'max' => 100,
            'eval' => 'trim',
            'softref' => 'typolink',
            'fieldControl' => [
                'linkPopup' => [],
            ],
        ],
    ],
    'terms_accepted' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.terms_accepted',
        'config' => [
            'type' => 'input',
            'renderType' => 'inputDateTime',
            'eval' => 'int,datetime',
            'default' => 0,
            'readOnly' => true,
        ],
    ],
    'verifiers' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.verifiers',
        'config' => [
            'type' => 'inline',
            'foreign_table' => 'tx_skills_domain_model_certifier',
            'foreign_field' => 'user',
            'foreign_label' => 'brand',
            'minitems' => 0,
            'maxitems' => 9999,
            'behaviour' => [
                'enableCascadingDelete' => false,
                'disableMovingChildrenWithParent' => true,
            ],
            'appearance' => [
                'collapseAll' => 1,
                'levelLinksPosition' => 'bottom',
                'showSynchronizationLink' => 0,
                'showPossibleLocalizationRecords' => 0,
                'useSortable' => 0,
                'showAllLocalizationLink' => 0,
            ],
        ],
    ],
    'anonymous' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.anonymous',
        'config' => [
            'type' => 'check',
            'items' => [
                1 => [
                    0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                ],
            ],
            'default' => 0,
        ],
    ],
    'api_key' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.api_key',
        'config' => [
            'type' => 'input',
            'size' => 50,
            'max' => 100,
            'eval' => 'trim',
            'default' => '',
        ],
    ],
    'monthly_activity' => [
        'exclude' => true,
        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user.monthly_activity',
        'config' => [
            'type' => 'input',
        ],
    ],
    'foreign_username' => [
        'exclude' => true,
        'label' => 'Foreign username',
        'config' => [
            'type' => 'input',
            'size' => 50,
            'max' => 255,
            'eval' => 'trim',
            'default' => '',
            'readOnly' => true,
        ],
    ],
    'data_sync' => [
        'exclude' => true,
        'label' => 'Data sync',
        'config' => [
            'type' => 'input',
            'renderType' => 'inputDateTime',
            'eval' => 'int,datetime',
            'default' => 0,
            'readOnly' => true,
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('fe_users', $tmp_skills_columns);

/* inherit and extend the show items from the parent class */

if (isset($GLOBALS['TCA']['fe_users']['types'][0]['showitem'])) {
    $GLOBALS['TCA']['fe_users']['types']['Tx_Skills_User']['showitem'] = $GLOBALS['TCA']['fe_users']['types'][0]['showitem'];
} elseif (is_array($GLOBALS['TCA']['fe_users']['types'])) {
    // use first entry in types array
    $fe_users_type_definition = reset($GLOBALS['TCA']['fe_users']['types']);
    $GLOBALS['TCA']['fe_users']['types']['Tx_Skills_User']['showitem'] = $fe_users_type_definition['showitem'];
} else {
    $GLOBALS['TCA']['fe_users']['types']['Tx_Skills_User']['showitem'] = '';
}
$GLOBALS['TCA']['fe_users']['types']['Tx_Skills_User']['showitem'] .= ',--div--;LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_user,';
$GLOBALS['TCA']['fe_users']['types']['Tx_Skills_User']['showitem'] .= 'publish_skills, avatar, favourite_certifiers, organisations, managed_brands, mail_push, mail_language, newsletter, terms_accepted, twitter, xing, linkedin, github, pending_email, profile_link, locked, anonymous, verifiers, api_key, foreign_username';

$GLOBALS['TCA']['fe_users']['columns'][$GLOBALS['TCA']['fe_users']['ctrl']['type']]['config']['items'][] = ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:fe_users.tx_extbase_type.Tx_Skills_User', 'Tx_Skills_User'];
$GLOBALS['TCA']['fe_users']['columns'][$GLOBALS['TCA']['fe_users']['ctrl']['type']]['config']['default'] = 'Tx_Skills_User';
