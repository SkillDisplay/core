<?php

return [
    'ctrl' => [
        'hideTable' => true,
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifierpermission',
        'label' => 'skill',
        'label_alt' => 'tier1, tier2, tier4',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_certifierpermission.gif',
    ],
    'types' => [
        1 => ['showitem' => 'certifier, skill, --palette--;;tiers'],
    ],
    'palettes' => [
        'tiers' => ['showitem' => 'tier2, tier1, tier4'],
    ],
    'columns' => [
        'tier1' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifierpermission.tier1',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled'],
                ],
                'default' => 0,
            ],
        ],
        'tier2' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifierpermission.tier2',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled'],
                ],
                'default' => 0,
            ],
        ],
        'tier4' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifierpermission.tier4',
            'config' => [
                'type' => 'check',
                'items' => [
                    ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled'],
                ],
                'default' => 0,
            ],
        ],
        'skill' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifierpermission.skill',
            'config' => [
                'type' => 'group',
                'foreign_table' => 'tx_skills_domain_model_skill',
                'allowed' => 'tx_skills_domain_model_skill',
                'size' => 1,
                'minitems' => 1,
                'maxitems' => 1,
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
        'certifier' => [
            'label' => 'Certifier',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
