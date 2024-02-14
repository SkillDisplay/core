<?php

return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification',
        'label' => 'user',
        'label_alt' => 'skill',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'searchFields' => 'revoke_reason,comment',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_certification.png',
    ],
    'types' => [
        1 => ['showitem' => '
            --palette--;;user, --palette--;;certifier, --palette--;;tiers, --palette--;;dates, --palette--;;revoke, comment,
            --div--;Static Data, --palette--;;credits, user_username, user_firstname, user_lastname, verifier_name, skill_title, brand_name, group_name
        '],
    ],
    'palettes' => [
        'user' => ['showitem' => 'user, skill, user_memberships'],
        'certifier' => ['showitem' => 'certifier, brand, campaign, request_group'],
        'tiers' => ['showitem' => 'tier3, tier2, tier1, tier4'],
        'dates' => ['showitem' => 'grant_date, deny_date, expire_date'],
        'revoke' => ['showitem' => 'revoke_date, revoke_reason'],
        'credits' => ['showitem' => 'points, price'],
    ],
    'columns' => [
        'crdate' => [
            'label' => 'crdate',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'tier1' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier1',
            'config' => [
                'type' => 'check',
                'items' => [
                    1 => [
                        0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                    ],
                ],
                'default' => 0,
            ],
        ],
        'tier2' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier2',
            'config' => [
                'type' => 'check',
                'items' => [
                    1 => [
                        0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                    ],
                ],
                'default' => 0,
            ],
        ],
        'tier3' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier3',
            'config' => [
                'type' => 'check',
                'items' => [
                    1 => [
                        0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                    ],
                ],
                'default' => 0,
            ],
        ],
        'tier4' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier4',
            'config' => [
                'type' => 'check',
                'items' => [
                    1 => [
                        0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                    ],
                ],
                'default' => 0,
            ],
        ],
        'grant_date' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.grant_date',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'dbType' => 'datetime',
                'size' => 12,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => null,
            ],
        ],
        'deny_date' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.deny_date',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'dbType' => 'datetime',
                'size' => 12,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => null,
            ],
        ],
        'expire_date' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.expire_date',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'dbType' => 'datetime',
                'size' => 12,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => null,
            ],
        ],
        'revoke_date' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.revoke_date',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'dbType' => 'datetime',
                'size' => 12,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => null,
            ],
        ],
        'revoke_reason' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.revoke_reason',
            'config' => [
                'type' => 'text',
                'cols' => 30,
                'rows' => 5,
                'eval' => 'trim',
            ],
        ],
        'skill' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.skill',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_skill',
                'foreign_table_where' => ' AND tx_skills_domain_model_skill.sys_language_uid IN (-1, 0) ORDER BY tx_skills_domain_model_skill.title',
            ],
        ],
        'user' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'foreign_table_where' => 'ORDER BY fe_users.username',
            ],
        ],
        'certifier' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.certifier',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_certifier',
                'items' => [
                    ['Certibot', 0],
                ],
                'default' => 0,
            ],
        ],
        'brand' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.brand',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_brand',
                'foreign_table_where' => 'AND tx_skills_domain_model_brand.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_brand.name',
                'items' => [
                    ['', 0],
                ],
                'default' => 0,
            ],
        ],
        'campaign' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.campaign',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_campaign',
                'foreign_table_where' => 'ORDER BY tx_skills_domain_model_campaign.title',
                'items' => [
                    ['', 0],
                ],
                'default' => 0,
            ],
        ],
        'request_group' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.request_group',
            'config' => [
                'type' => 'input',
                'size' => 35,
                'max' => 50,
                'eval' => 'trim',
            ],
        ],
        'comment' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.comment',
            'config' => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 5,
                'eval' => 'trim',
            ],
        ],
        'skill_title' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.skill_title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'user_username' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.user_username',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'user_firstname' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.user_firstname',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'user_lastname' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.user_lastname',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'verifier_name' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.verifier_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'brand_name' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.brand_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim,required',
            ],
        ],
        'group_name' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.group_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim',
            ],
        ],
        'rewardable' => [
            'exclude' => true,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.rewardable',
            'config' => [
                'type' => 'check',
                'items' => [
                    1 => [
                        0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled',
                    ],
                ],
                'default' => 1,
            ],
        ],
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
        'user_memberships' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_cerification.usermemberships',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_skills_domain_model_membershiphistory',
                'foreign_field' => 'verification',
                'foreign_label' => 'brand_name',
                'behaviour' => [
                    'enableCascadingDelete' => true,
                ],
                'appearance' => [
                    'collapseAll' => true,
                    'levelLinksPosition' => 'top',
                    'showSynchronizationLink' => 0,
                    'showPossibleLocalizationRecords' => 0,
                    'useSortable' => 0,
                    'showAllLocalizationLink' => 0,
                ],
            ],
        ],
    ],
];
