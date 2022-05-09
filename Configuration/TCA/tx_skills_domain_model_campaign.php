<?php
return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_campaign',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_campaign.png',
        'searchFields' => 'title,user'
    ],
    'types' => [
        ['showitem' => 'title,user'],
    ],
    'columns' => [
	    'title' => [
	        'exclude' => false,
	        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_campaign.title',
	        'config' => [
			    'type' => 'input',
			    'size' => 30,
                'max' => 255,
			    'eval' => 'trim,required'
			],
	    ],
        'user' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_campaign.user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'foreign_table_where' => 'ORDER BY fe_users.username',
            ],
        ],
    ],
];
