<?php

return [
    'ctrl' => [
        'hideTable' => true,
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_rewardprerequisite',
        'label' => 'reward',
        'label_alt' => 'reward, skill, level',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_reward.svg',
    ],
    'types' => [
        1 => ['showitem' => 'reward, skill, level, brand'],
    ],
    'columns' => [
        'reward' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'skill' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_rewardprerequisite.skill',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_skill',
                'foreign_table_where' => 'AND tx_skills_domain_model_skill.sys_language_uid IN (-1,0) ORDER BY tx_skills_domain_model_skill.title',
            ],
        ],
        'level' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_rewardprerequisite.level',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier1', 'value' => 1],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier2', 'value' => 2],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier3', 'value' => 3],
                    ['label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier4', 'value' => 4],
                ],
            ],
        ],
        'brand' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_rewardprerequisite.brand',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_brand',
                'foreign_table_where' => 'AND tx_skills_domain_model_brand.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_brand.name',
                'items' => [
                    ['label' => '', 'value' => 0],
                ],
                'default' => 0,
            ],
        ],
    ],
];
