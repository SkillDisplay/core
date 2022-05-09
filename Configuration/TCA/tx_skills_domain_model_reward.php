<?php
return [
    'ctrl' => [
        'groupName' => 'skills_rewards',
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_reward.svg'
    ],
    'types' => [
        1 => ['showitem' => 'type, title, category, reward, pdf_layout_file, description, brand, detail_link, availability_start, availability_end, valid_until, active, valid_for_organisation, --palette--;;linkeditems, prerequisites, syllabus_layout_file'],
    ],
    'palettes' => [
        'linkeditems' => [
            'label' => 'Required SkillSet (overrides prerequisites if selected)',
            'description' => 'Selecting a SkillSet makes the selection of single requirements below ineffective. Only this configuration is respected then.',
            'showitem' => 'skillpath, level',
        ]
    ],
    'columns' => [
        'type' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.type.badge', \SkillDisplay\Skills\Domain\Model\Reward::TYPE_BADGE],
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.type.certificate', \SkillDisplay\Skills\Domain\Model\Reward::TYPE_CERTIFICATE],
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.type.affiliate', \SkillDisplay\Skills\Domain\Model\Reward::TYPE_AFFILIATE],
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.type.download', \SkillDisplay\Skills\Domain\Model\Reward::TYPE_DOWNLOAD],
                ]
            ],
        ],
        'title' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 50,
                'eval' => 'trim,required'
            ],
        ],
        'reward' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.reward',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputLink',
                'size' => 30,
                'max' => 100,
                'eval' => 'trim',
                'softref' => 'typolink',
                'fieldControl' => [
                    'linkPopup' => [],
                ],
            ],
        ],
        'pdf_layout_file' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.pdf_layout_file',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'sys_file',
                'foreign_table' => 'sys_file',
                'appearance' => [
                    'elementBrowserAllowed' => 'html',
                    'elementBrowserType' => 'file'
                ],
                'fieldWizard' => ['recordsOverview' => ['disabled' => true]],
                'size' => 1,
                'maxitems' => 1,
                'minitems' => 0,
                'default' => 0,
            ]
        ],
        'syllabus_layout_file' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.syllabus_layout_file',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'sys_file',
                'foreign_table' => 'sys_file',
                'appearance' => [
                    'elementBrowserAllowed' => 'pdf',
                    'elementBrowserType' => 'file'
                ],
                'size' => 1,
                'maxitems' => 1,
                'minitems' => 0,
                'default' => 0,
            ]
        ],
        'description' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.description',
            'config' => [
                'type' => 'text',
                'cols' => 50,
                'rows' => 3,
                'max' => 80,
                'eval' => 'trim,required'
            ],
        ],
        'detail_link' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.detail_link',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputLink',
                'size' => 30,
                'max' => 100,
                'eval' => 'trim',
                'softref' => 'typolink',
                'fieldControl' => [
                    'linkPopup' => [],
                ],
            ],
        ],
        'availability_start' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.availability_start',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'default' => 0,
                'eval' => 'datetime'
            ]
        ],
        'availability_end' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.availability_end',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'default' => 0,
                'eval' => 'datetime'
            ]
        ],
        'valid_until' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.valid_until',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'default' => 0,
                'eval' => 'datetime',
            ]
        ],
        'valid_for_organisation' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.valid_for_organisation',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_skills_domain_model_brand',
                'foreign_table_where' => 'AND tx_skills_domain_model_brand.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_brand.name',
                'items' => [
                    ['', 0]
                ],
                'default' => 0
            ],
        ],
	    'prerequisites' => [
	        'exclude' => false,
	        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.prerequisites',
	        'config' => [
			    'type' => 'inline',
			    'foreign_table' => 'tx_skills_domain_model_rewardprerequisite',
			    'foreign_field' => 'reward',
                'minitems' => 0,
			    'maxitems' => 9999,
			    'appearance' => [
			        'collapseAll' => 1,
			        'levelLinksPosition' => 'top',
			        'showSynchronizationLink' => 1,
			        'showPossibleLocalizationRecords' => 1,
			        'useSortable' => 0,
			        'showAllLocalizationLink' => 1
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
            ],
        ],
        'skillpath' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_reward.skillpath',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        '',
                        0
                    ]
                ],
                'default' => 0,
                'foreign_table' => 'tx_skills_domain_model_skillpath',
                'foreign_table_where' => 'AND tx_skills_domain_model_skillpath.sys_language_uid IN (0,-1) ORDER BY tx_skills_domain_model_skillpath.name',
            ],
        ],
        'level' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_rewardprerequisite.level',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier1', 1],
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier2', 2],
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier3', 3],
                    ['LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_certification.tier4', 4],
                ],
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
