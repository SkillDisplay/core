<?php

return [
    'ctrl' => [
        'hideTable' => true,
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_link',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'searchFields' => 'title,url,uuid',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_link.png',
    ],
    'types' => [
        1 => ['showitem' => 'skill, --palette--;;title, --div--;Import, uuid, imported'],
    ],
    'palettes' => [
        'title' => ['showitem' => 'title, url'],
    ],
    'columns' => [
        'title' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_link.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'url' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_link.url',
            'config' => [
                'type' => 'input',
                'size' => 255,
                'max' => 255,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'color' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_link.color',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'Certifiers', 'value' => '#2361ab'],
                    ['label' => 'Educators', 'value' => '#2cace4'],
                    ['label' => 'Learners', 'value' => '#6fb885'],
                    ['label' => 'Business persons', 'value' => '#1e8b6b'],
                ],
            ],
        ],
        'skill' => [
            'l10n_mode' => 'exclude',
            'label' => 'Skill',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'tablename' => [
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'passthrough',
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
    ],
];
