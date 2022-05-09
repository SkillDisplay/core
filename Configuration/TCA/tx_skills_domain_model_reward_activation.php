<?php
return [
    'ctrl' => [
        'groupName' => 'skills_reward',
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward_activation',
        'label' => 'user',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
    ],
    'types' => [
        1 => ['showitem' => 'reward, active'],
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
        'active' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.active',
            'config' => [
                'type' => 'check',
                'default' => 0
            ]
        ],
    ],
];
