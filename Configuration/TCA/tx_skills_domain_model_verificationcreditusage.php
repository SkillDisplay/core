<?php

return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditusage',
        'label' => 'credit_pack',
        'label_alt' => 'verification',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_orderby' => 'crdate DESC',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_verificationcreditusage.svg',
    ],
    'types' => [
        1 => ['showitem' => 'credit_pack, verification, points, price'],
    ],
    'columns' => [
        'points' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditusage.points',
            'config' => [
                'type' => 'input',
                'size' => 5,
                'max' => 5,
                'eval' => 'required,int',
            ],
        ],
        'price' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditusage.price',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'max' => 10,
                'range' => [
                    'upper' => 999999.99,
                    'lower' => 0.00,
                ],
                'default' => 0.00,
                'eval' => 'required,double2',
            ],
        ],
        'credit_pack' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditusage.credit_pack',
            'config' => [
                'type' => 'group',
                'foreign_table' => 'tx_skills_domain_model_verificationcreditpack',
                'allowed' => 'tx_skills_domain_model_verificationcreditpack',
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
        'verification' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_verificationcreditusage.verification',
            'config' => [
                'type' => 'group',
                'foreign_table' => 'tx_skills_domain_model_certification',
                'allowed' => 'tx_skills_domain_model_certification',
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
    ],
];
