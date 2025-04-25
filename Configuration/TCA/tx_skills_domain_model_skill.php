<?php

use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Hook\RecordLabels;
use TYPO3\CMS\Core\Resource\File;

return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill',
        'label' => 'title',
        'label_userFunc' => RecordLabels::class . '->skill',
        'descriptionColumn' => 'int_notes',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'title,description,goals,uuid',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_skill.png',
    ],
    'types' => [
        0 => ['showitem' => '
            --palette--;;language, title, path_segment, description, goals,
            --div--;LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tab.tags, domain_tag, tags,
            --div--;LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tab.links, links,
            --div--;LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tab.ownership, brands, owner,
            --div--;LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tab.status, dormant, placeholder,
            --div--;LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tab.visualization, icon, image,
            --div--;LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tab.requirements, requirements,
            --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access, hidden, starttime, endtime, visibility,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:notes, int_notes,
            --div--;Import, uuid, imported',
        ],
    ],
    'palettes' => [
        'language' => ['showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource'],
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
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table' => 'tx_skills_domain_model_skill',
                'foreign_table_where' => 'AND tx_skills_domain_model_skill.pid=###CURRENT_PID### AND tx_skills_domain_model_skill.sys_language_uid IN (-1,0)',
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
                    ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled'],
                ],
                'default' => 0,
            ],
        ],
        'starttime' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.starttime',
            'config' => [
                'type' => 'datetime',
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
                'type' => 'datetime',
                'default' => 0,
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'title' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim',
                'required' => true,
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
                    'fields' => ['title'],
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
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
                'eval' => 'trim',
                'enableRichtext' => true,
                'required' => true,
            ],
        ],
        'goals' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.goals',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 10,
                'eval' => 'trim',
                'enableRichtext' => true,
                'required' => true,
            ],
        ],
        'icon' => [
            'exclude' => false,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.icon',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'image' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.image',
            'config' => [
                //## !!! Watch out for fieldName different from columnName
                'type' => 'file',
                'allowed' => $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'],
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
                'maxitems' => 1,
            ],
        ],
        'placeholder' => [
            'exclude' => false,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.placeholder',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled'],
                ],
                'default' => 0,
            ],
        ],
        'dormant' => [
            'exclude' => false,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.dormant',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
            ],
        ],
        'brands' => [
            'exclude' => true,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.brands',
            'config' => [
                'type' => 'group',
                'foreign_table' => 'tx_skills_domain_model_brand',
                'allowed' => 'tx_skills_domain_model_brand',
                'MM' => 'tx_skills_skill_brand_mm',
                'size' => 10,
                'autoSizeMax' => 30,
                'minitems' => 0,
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
        'tags' => [
            'exclude' => false,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.tags',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_skills_domain_model_tag',
                'foreign_table_where' => 'AND tx_skills_domain_model_tag.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_tag.title',
                'MM' => 'tx_skills_skill_tag_mm',
                'size' => 10,
                'autoSizeMax' => 30,
                'minitems' => 0,
                'maxitems' => 9999,
                'multiple' => 0,
                'fieldControl' => [
                    'editPopup' => [
                        'disabled' => false,
                    ],
                    'addRecord' => [
                        'disabled' => false,
                    ],
                ],
            ],
        ],
        'domain_tag' => [
            'exclude' => false,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.domain_tag',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_skills_domain_model_tag',
                'foreign_table_where' => 'AND tx_skills_domain_model_tag.sys_language_uid IN (0,-1) AND tx_skills_domain_model_tag.domain_tag = 1 ORDER BY tx_skills_domain_model_tag.title',
                'minitems' => 0,
                'maxitems' => 1,
                'default' => 0,
            ],
        ],
        'owner' => [
            'exclude' => true,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.owner',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'foreign_table_where' => 'ORDER BY fe_users.username',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'default' => 0,
            ],
        ],
        'links' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.links',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_skills_domain_model_link',
                'foreign_field' => 'skill',
                'foreign_sortby' => 'sorting',
                'foreign_table_field' => 'tablename',
                'minitems' => 0,
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 0,
                    'levelLinksPosition' => 'top',
                    'useSortable' => 1,
                ],
            ],
        ],
        'requirements' => [
            'exclude' => false,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.requirements',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_skills_domain_model_requirement',
                'foreign_field' => 'skill',
                'foreign_sortby' => 'sorting',
                'minitems' => 0,
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => true,
                    'levelLinksPosition' => 'both',
                    'showSynchronizationLink' => 1,
                    'showPossibleLocalizationRecords' => 1,
                    'useSortable' => 1,
                    'showAllLocalizationLink' => 1,
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
                'type' => 'datetime',
                'size' => 12,
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'int_notes' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skill.int_notes',
            'config' => [
                'type' => 'text',
                'rows' => 10,
                'cols' => 30,
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
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.visibility.public', 'value' => Skill::VISIBILITY_PUBLIC],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_skillpath.visibility.members', 'value' => Skill::VISIBILITY_ORGANISATION],
                ],
                'default' => Skill::VISIBILITY_ORGANISATION,
            ],
        ],
    ],
];
