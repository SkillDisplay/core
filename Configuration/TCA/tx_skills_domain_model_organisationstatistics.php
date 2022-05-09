<?php
return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics',
        'label' => 'brand',
        'hideTable' => true,
        'rootLevel' => 1,
    ],
    'types' => [
        0 => ['showitem' => ''],
    ],
    'columns' => [
        'brand' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.brand',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_brand',
                'foreign_table_where' => 'AND tx_skills_domain_model_brand.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_brand.name',
            ],
        ],
        'total_score' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.total_score',
            'config' => [
                'type' => 'input'
            ],
        ],
        'current_month_users' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.current_month_users',
            'config' => [
                'type' => 'input'
            ],
        ],
        'last_month_users' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.last_month_users',
            'config' => [
                'type' => 'input'
            ],
        ],
        'current_month_verifications' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.current_month_verifications',
            'config' => [
                'type' => 'input'
            ],
        ],
        'last_month_verifications' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.current_month_verifications',
            'config' => [
                'type' => 'input'
            ],
        ],
        'current_month_issued' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.current_month_issued',
            'config' => [
                'type' => 'input'
            ],
        ],
        'last_month_issued' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.last_month_issued',
            'config' => [
                'type' => 'input'
            ],
        ],
        'monthly_scores' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.monthly_scores',
            'config' => [
                'type' => 'input'
            ],
        ],
        'interests' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.interests',
            'config' => [
                'type' => 'input'
            ],
        ],
        'potential' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.potential',
            'config' => [
                'type' => 'input'
            ],
        ],
        'composition' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_organisationstatistics.composition',
            'config' => [
                'type' => 'input'
            ],
        ],
        'sum_verifications' => [
            'exclude' => true,
            'label' => 'Sum of all verifications',
            'config' => [
                'type' => 'input'
            ],
        ],
        'sum_supported_skills' => [
            'exclude' => true,
            'label' => 'Sum of all supported skills',
            'config' => [
                'type' => 'input'
            ],
        ],
        'sum_skills' => [
            'exclude' => true,
            'label' => 'Sum of all owned skills',
            'config' => [
                'type' => 'input'
            ],
        ],
        'sum_issued' => [
            'exclude' => true,
            'label' => 'All issued',
            'config' => [
                'type' => 'input'
            ],
        ],
        'expertise' => [
            'exclude' => true,
            'label' => 'Expertise of brand members',
            'config' => [
                'type' => 'input'
            ],
        ],
    ]
];
