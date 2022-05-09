<?php
return [
    'ctrl' => [
        'groupName' => 'skills_rewards',
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_grantedreward',
        'label' => 'user',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_grantedreward.svg'
    ],
    'types' => [
        1 => ['showitem' => 'reward, user, valid_until, selected_by_user, position_index'],
    ],
    'columns' => [
	    'reward' => [
	        'exclude' => false,
	        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_grantedreward.reward',
	        'config' => [
			    'type' => 'select',
                'renderType' => 'selectSingle',
			    'foreign_table' => 'tx_skills_domain_model_reward',
                'foreign_table_where' => 'ORDER BY tx_skills_domain_model_reward.title',
			],
	    ],
        'user' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_grantedreward.user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'foreign_table_where' => 'ORDER BY fe_users.username',
            ],
        ],
        'valid_until' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_grantedreward.valid_until',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'eval' => 'datetime',
                'default' => 0
            ]
        ],
        'selected_by_user' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_grantedreward.selected_by_user',
            'config' => [
                'type' => 'check',
                'default' => 0
            ]
        ],
        'position_index' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_grantedreward.position_index',
            'config' => [
                'type' => 'input',
                'size' => 4,
                'eval' => 'int'
            ]
        ],

    ],
];
