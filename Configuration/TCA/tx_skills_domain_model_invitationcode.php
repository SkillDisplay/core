<?php

return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_invitationcode',
        'label' => 'code',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_orderby' => 'code',
        'enablecolumns' => [
            'endtime' => 'expires',
        ],
        'searchFields' => 'code',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_tag.png',
    ],
    'types' => [
        0 => ['showitem' => 'code,brand,expires,created_by,used_by,used_at'],
    ],
    'columns' => [
        'code' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_invitationcode.code',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'brand' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_invitationcode.brand',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_brand',
                'foreign_table_where' => 'AND tx_skills_domain_model_brand.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_brand.name',
            ],
        ],
        'expires' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_invitationcode.expires',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
            ],
        ],
        'created_by' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_invitationcode.created_by',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'foreign_table_where' => 'ORDER BY fe_users.username',
            ],
        ],
        'used_by' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_invitationcode.used_by',
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
        'used_at' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_invitationcode.used_at',
            'config' => [
                'type' => 'datetime',
                'default' => 0,
            ],
        ],
    ],
];
