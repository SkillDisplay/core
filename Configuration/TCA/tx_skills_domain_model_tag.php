<?php

return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_tag',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'sortby' => 'sorting',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'searchFields' => 'title,uuid',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_tag.png',
    ],
    'types' => [
        1 => ['showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource, title, description, domain_tag, domain_tagged_skills, tagged_skills, --div--;Import, uuid, imported'],
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
                'foreign_table' => 'tx_skills_domain_model_tag',
                'foreign_table_where' => 'AND tx_skills_domain_model_tag.pid=###CURRENT_PID### AND tx_skills_domain_model_tag.sys_language_uid IN (-1,0)',
                'default' => 0,
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'title' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_tag.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'description' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_tag.description',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
                'eval' => 'trim',
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
        ], /*
        'tagged_skills' => [
            'exclude' => false,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_tag.taggedSkills',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tx_skills_domain_model_skill',
                'foreign_table' => 'tx_skills_domain_model_skill',
                'MM' => 'tx_skills_skill_tag_mm',
                'MM_opposite_field' => 'tags',
                'foreign_label' => 'title',
                'minitems' => 0,
                'maxitems' => 9999,
                'behaviour' => [
                    'enableCascadingDelete' => false,
                ],
                'appearance' => [
                    'collapseAll' => 1,
                    'levelLinksPosition' => 'none',
                    'showSynchronizationLink' => 0,
                    'showPossibleLocalizationRecords' => 0,
                    'useSortable' => 0,
                    'showAllLocalizationLink' => 0,
                ],
            ],
        ],
        'domain_tagged_skills' => [
            'exclude' => false,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_tag.domainTaggedSkills',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_skills_domain_model_skill',
                'foreign_field' => 'domain_tag',
                'foreign_label' => 'title',
                'minitems' => 0,
                'maxitems' => 9999,
                'behaviour' => [
                    'enableCascadingDelete' => false,
                ],
                'appearance' => [
                    'collapseAll' => 1,
                    'levelLinksPosition' => 'none',
                    'showSynchronizationLink' => 0,
                    'showPossibleLocalizationRecords' => 0,
                    'useSortable' => 0,
                    'showAllLocalizationLink' => 0,
                ],
            ],
        ],*/
        'domain_tag' => [
            'exclude' => true,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_tag.domain_tag',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled'],
                ],
                'default' => 0,
            ],
        ],
    ],
];
