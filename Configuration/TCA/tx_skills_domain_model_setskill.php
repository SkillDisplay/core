<?php
return [
    'ctrl' => [
        'hideTable' => true,
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_setskill',
        'label' => 'skill',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_setskill.png'
    ],
    'types' => [
        1 => ['showitem' => 'tx_set, skill'],
    ],
    'columns' => [
	    'skill' => [
	        'exclude' => false,
	        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_setskill.skill',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'foreign_table' => 'tx_skills_domain_model_skill',
                'allowed' => 'tx_skills_domain_model_skill',
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
        'tx_set' => [
            'label' => 'SetSkill',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
