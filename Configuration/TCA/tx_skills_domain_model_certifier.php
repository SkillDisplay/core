<?php
return [
    'ctrl' => [
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifier',
        'label' => 'user',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
		'delete' => 'deleted',
        'enablecolumns' => [
			'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_certifier.gif'
    ],
    'types' => [
        1 => ['showitem' => 'hidden, --palette--;;properties, shared_api_secret, permissions'],
    ],
    'palettes' => [
        'properties' => ['showitem' => 'user, brand, link, test_system']
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'items' => [
                    1 => [
                        0 => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.enabled'
                    ]
                ],
            ],
        ],
	    'user' => [
	        'exclude' => false,
	        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifier.user',
	        'config' => [
			    'type' => 'select',
			    'renderType' => 'selectSingle',
			    'foreign_table' => 'fe_users',
                'foreign_table_where' => 'ORDER BY fe_users.username',
                'items' => [['','']],
                'default' => ''
			],
	    ],
	    'brand' => [
	        'exclude' => false,
	        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifier.brand',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'foreign_table' => 'tx_skills_domain_model_brand',
                'allowed' => 'tx_skills_domain_model_brand',
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
        'link' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifier.link',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputLink',
                'size' => 30,
                'max' => 255,
                'eval' => 'trim',
                'softref' => 'typolink',
                'fieldControl' => [
                    'linkPopup' => [],
                ],
            ]
        ],
	    'permissions' => [
	        'exclude' => false,
	        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifier.permissions',
	        'config' => [
			    'type' => 'inline',
			    'foreign_table' => 'tx_skills_domain_model_certifierpermission',
			    'foreign_field' => 'certifier',
                'minitems' => 0,
			    'maxitems' => 9999,
			    'appearance' => [
			        'collapseAll' => true,
			        'levelLinksPosition' => 'top',
			        'showSynchronizationLink' => true,
			        'showPossibleLocalizationRecords' => true,
			        'showAllLocalizationLink' => true
			    ],
			],
	    ],
        'shared_api_secret' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifier.shared_api_secret',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 100,
                'eval' => 'trim'
            ],
        ],
        'test_system' => [
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certifier.testsystem',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'itemsProcFunc' => \SkillDisplay\Skills\Service\TestSystemProviderService::class . '->' . 'getProviderListForTCA',
                'items' => []
            ]
        ],
    ],
];
