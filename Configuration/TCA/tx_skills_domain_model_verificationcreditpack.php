<?php

return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack',
        'label' => 'title',
        'label_alt' => 'brand_name',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_orderby' => 'crdate DESC',
        'searchFields' => 'title,brand_name',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_verificationcreditpack.svg',
    ],
    'types' => [
        1 => ['showitem' => 'valuta, valid_thru, brand, title, current_points, initial_points, price, price_charged, user, invoice_number,
          --div--;Static data, brand_name, user_username, user_firstname, user_lastname'],
    ],
    'columns' => [
        'valuta' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.valuta',
            'config' => [
                'type' => 'datetime',
                'size' => 15,
                'required' => true,
            ],
        ],
        'valid_thru' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.valid_thru',
            'config' => [
                'type' => 'datetime',
                'size' => 15,
                'default' => 0,
            ],
        ],
        'brand' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.brand',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_brand',
                'foreign_table_where' => 'AND tx_skills_domain_model_brand.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_brand.name',
            ],
        ],
        'title' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 200,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'current_points' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.current_points',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'readOnly' => true,
            ],
        ],
        'initial_points' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.initial_points',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'max' => 50,
                'required' => true,
            ],
        ],
        'price' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.price',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'max' => 10,
                'range' => [
                    'upper' => 999999.99,
                    'lower' => 0.00,
                ],
                'default' => 0.00,
                'format' => 'decimal',
                'required' => true,
            ],
        ],
        'price_charged' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.price_charged',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'max' => 10,
                'range' => [
                    'upper' => 999999.99,
                    'lower' => 0.00,
                ],
                'default' => 0.00,
                'format' => 'decimal',
                'required' => true,
            ],
        ],
        'user' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.user',
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
        'invoice_number' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.invoice_number',
            'config' => [
                'type' => 'input',
                'size' => 35,
                'max' => 100,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'brand_name' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.brand_name',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'user_username' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.user_username',
            'config' => [
                'type' => 'input',
                'size' => 35,
                'max' => 255,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'user_firstname' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.user_firstname',
            'config' => [
                'type' => 'input',
                'size' => 35,
                'max' => 50,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
        'user_lastname' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditpack.user_lastname',
            'config' => [
                'type' => 'input',
                'size' => 35,
                'max' => 50,
                'eval' => 'trim',
                'default' => '',
            ],
        ],
    ],
];
