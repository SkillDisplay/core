<?php

return [
    'ctrl' => [
        'hideTable' => true,
        'title'	=> 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_requirement',
        'label' => 'sets',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'iconfile' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_requirement.png',
    ],
    'types' => [
        1 => ['showitem' => 'skill, sets'],
    ],
    'columns' => [
        'sets' => [
            'exclude' => false,
            'label' => 'LLL:EXT:skills/Resources/Private/Language/locallang_db.xlf:tx_skills_domain_model_requirement.sets',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_skills_domain_model_set',
                'foreign_field' => 'requirement',
                'foreign_sortby' => 'sorting',
                'minitems' => 1,
                'maxitems' => 9999,
                'appearance' => [
                    'collapseAll' => 0,
                    'levelLinksPosition' => 'top',
                    'showSynchronizationLink' => 1,
                    'showPossibleLocalizationRecords' => 1,
                    'useSortable' => 1,
                    'showAllLocalizationLink' => 1,
                ],
            ],

        ],
        'skill' => [
            'label' => 'Skill',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
