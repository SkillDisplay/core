<?php

return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award',
        'label' => 'user',
        'hideTable' => true,
        'rootLevel' => 1,
    ],
    'types' => [
        1 => ['showitem' => ''],
    ],
    'columns' => [
        'user' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'foreign_table_where' => 'ORDER BY fe_users.username',
            ],
        ],
        'brand' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.brand',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_brand',
                'foreign_table_where' => 'AND tx_skills_domain_model_brand.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_brand.name',
            ],
        ],
        'type' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'size' => 1,
                'items' => [
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.type.verified', 'value' => 0],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.type.member', 'value' => 1],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.type.coach', 'value' => 2],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.type.mentor', 'value' => 3],
                ],
                'default' => 0,
            ],
        ],
        'level' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.level',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'size' => 1,
                'items' => [
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.level.undefined', 'value' => 0],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.level.certificate', 'value' => 1],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.level.educational', 'value' => 2],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.level.self', 'value' => 3],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.level.business', 'value' => 4],
                ],
                'default' => 1,
            ],
        ],
        'rank' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.rank',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'size' => 1,
                'items' => [
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.rank.bronze', 'value' => 0],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.rank.silver', 'value' => 1],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.rank.gold', 'value' => 2],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.rank.platinum', 'value' => 3],
                ],
                'default' => 0,
            ],
        ],
    ],
];
