<?php
return [
    'ctrl' => [
        'hideTable' => true,
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_set',
        'label' => 'skills',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_set.png'
    ],
    'types' => [
        1 => ['showitem' => 'requirement, skills'],
    ],
    'columns' => [
	    'skills' => [
	        'exclude' => false,
	        'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_set.skills',
	        'config' => [
			    'type' => 'inline',
			    'foreign_table' => 'tx_skills_domain_model_setskill',
			    'foreign_field' => 'tx_set',
			    'foreign_sortby' => 'sorting',
                'minitems' => 1,
			    'maxitems' => 9999,
			    'appearance' => [
			        'collapseAll' => 0,
			        'levelLinksPosition' => 'top',
			        'showSynchronizationLink' => 1,
			        'showPossibleLocalizationRecords' => 1,
			        'useSortable' => 1,
			        'showAllLocalizationLink' => 1
			    ],
			],

	    ],
        'requirement' => [
            'label' => 'Requirement',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
