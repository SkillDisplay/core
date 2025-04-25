<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_membershiphistory',
        'label' => 'verification',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
    ],
    'types' => [
        0 => ['showitem' => 'verification,brand,brand_name'],
    ],
    'columns' => [
        'verification' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_award.user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_certification',
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
        'brand_name' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_brand.name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
    ],
];
