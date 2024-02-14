<?php

use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Hook\SkillsProcFunc;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'default_sortby' => 'name',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'name,description,uuid',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_skillpath.png',
    ],
    'types' => [
        1 => ['showitem' => '
            --palette--;;language, name, path_segment, categories, description, visibility, --palette--;;brand, --palette--;;legitimation, skills, links,
            --div--;Files, syllabus_layout_file, certificate_link, certificate_layout_file,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access, hidden, starttime, endtime,
            --div--;Import, uuid, imported',
        ],
    ],
    'palettes' => [
        'language' => ['showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource'],
        'brand' => ['showitem' => 'brands, media'],
        'legitimation' => ['showitem' => 'legitimation_user, legitimation_date'],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => ['type' => 'language'],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_skills_domain_model_skillpath',
                'foreign_table_where' => 'AND tx_skills_domain_model_skillpath.pid=###CURRENT_PID### AND tx_skills_domain_model_skillpath.sys_language_uid IN (-1,0)',
                'default' => 0,
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'items' => [
                    1 => [
                        0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                    ],
                ],
            ],
        ],
        'starttime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.starttime',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'endtime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'name' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'path_segment' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:pages.slug',
            'displayCond' => 'VERSION:IS:false',
            'config' => [
                'type' => 'slug',
                'size' => 50,
                'generatorOptions' => [
                    'fields' => ['name'],
                    'replacements' => [
                        '/' => '-',
                    ],
                ],
                'fallbackCharacter' => '-',
                'eval' => 'unique',
                'default' => '',
            ],
        ],
        'description' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
                'eval' => 'trim,required',
                'enableRichtext' => true,
            ],
        ],
        'media' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.media',
            'config' => ExtensionManagementUtility::getFileFieldTCAConfig(
                'media',
                [
                    'appearance' => [
                        'createNewRelationLinkTitle' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:images.addFileReference',
                    ],
                    'overrideChildTca' => [
                        'types' => [
                            0 => [
                                'showitem' => '
                                --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette',
                            ],
                            File::FILETYPE_TEXT => [
                                'showitem' => '
                                --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette',
                            ],
                            File::FILETYPE_IMAGE => [
                                'showitem' => '
                                --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette',
                            ],
                            File::FILETYPE_AUDIO => [
                                'showitem' => '
                                --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette',
                            ],
                            File::FILETYPE_VIDEO => [
                                'showitem' => '
                                --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette',
                            ],
                            File::FILETYPE_APPLICATION => [
                                'showitem' => '
                                --palette--;LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file_reference.imageoverlayPalette;imageoverlayPalette,
                                --palette--;;filePalette',
                            ],
                        ],
                    ],
                    'maxitems' => 999,
                ],
                $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
            ),
        ],
        'brands' => [
            'exclude' => false,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.brands',
            'config' => [
                'type' => 'group',
                'foreign_table' => 'tx_skills_domain_model_brand',
                'allowed' => 'tx_skills_domain_model_brand',
                'MM' => 'tx_skills_skillset_brand_mm',
                'size' => 5,
                'autoSizeMax' => 10,
                'minitems' => 1,
                'maxitems' => 9999,
                'fieldControl' => [
                    'editPopup' => [
                        'disabled' => true,
                    ],
                    'addRecord' => [
                        'disabled' => true,
                    ],
                ],
            ],
        ],
        'legitimation_user' => [
            'exclude' => true,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.legitimation_user',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_users',
                'foreign_table' => 'fe_users',
                'size' => 1,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'legitimation_date' => [
            'exclude' => true,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.legitimation_date',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 15,
                'eval' => 'date',
                'default' => 0,
            ],
        ],
        'skills' => [
            'exclude' => false,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.skills',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'itemsProcFunc' => SkillsProcFunc::class . '->checkForReadableSkills',
                'foreign_table' => 'tx_skills_domain_model_skill',
                'foreign_table_where' => ' AND tx_skills_domain_model_skill.sys_language_uid IN (-1, 0) ORDER BY tx_skills_domain_model_skill.title',
                'MM' => 'tx_skills_skillpath_skill_mm',
                'size' => 10,
                'autoSizeMax' => 30,
                'minitems' => 1,
                'maxitems' => 9999,
                'multiple' => 0,
            ],
        ],
        'links' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.links',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_skills_domain_model_link',
                'foreign_field' => 'skill',
                'foreign_table_field' => 'tablename',
                'foreign_sortby' => 'sorting',
                'minitems' => 0,
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 0,
                    'levelLinksPosition' => 'top',
                    'useSortable' => 1,
                ],
            ],
        ],
        'uuid' => [
            'exclude' => true,
            'label' => 'UUID',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
                'default' => '',
            ],
        ],
        'imported' => [
            'exclude' => true,
            'label' => 'Imported on',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 12,
                'eval' => 'datetime',
                'default' => '0',
                'readOnly' => true,
            ],
        ],
        'syllabus_layout_file' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.syllabus_layout_file',
            'config' => [
                'type' => 'group',
                'allowed' => 'sys_file',
                'foreign_table' => 'sys_file',
                'appearance' => [
                    'elementBrowserAllowed' => 'pdf',
                    'elementBrowserType' => 'file',
                ],
                'size' => 1,
                'maxitems' => 1,
                'minitems' => 0,
                'default' => 0,
            ],
        ],
        'certificate_link' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.certificate_link',
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
        'certificate_layout_file' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.certificate_layout_file',
            'config' => [
                'type' => 'group',
                'allowed' => 'sys_file',
                'foreign_table' => 'sys_file',
                'appearance' => [
                    'elementBrowserAllowed' => 'html',
                    'elementBrowserType' => 'file',
                ],
                'fieldWizard' => ['recordsOverview' => ['disabled' => true]],
                'size' => 1,
                'maxitems' => 1,
                'minitems' => 0,
                'default' => 0,
            ],
        ],
        'visibility' => [
            'exclude' => true,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.visibility',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.visibility.public', SkillPath::VISIBILITY_PUBLIC],
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.visibility.members', SkillPath::VISIBILITY_ORGANISATION],
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.visibility.link', SkillPath::VISIBILITY_LINK],
                ],
                'default' => SkillPath::VISIBILITY_ORGANISATION,
            ],
        ],
        'popularity_log2' => [
            'exclude' => true,
            'label' => 'Popularity factor (log2)',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'eval' => 'trim',
                'default' => '0.0',
            ],
        ],
        'categories' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_category.categories',
            'config' => [
                'type' => 'category',
                'relationship' => 'oneToOne',
                'size' => 5,
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
    ],
];
