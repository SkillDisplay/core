<?php declare(strict_types = 1);

return [
    \TYPO3\CMS\Extbase\Domain\Model\FrontendUser::class => [
        'subclasses' => [
            'Tx_Skills_User' => \SkillDisplay\Skills\Domain\Model\User::class
        ]
    ],
    \SkillDisplay\Skills\Domain\Model\User::class => [
        'tableName' => 'fe_users',
        'recordType' => 'Tx_Skills_User',
    ],
    \SkillDisplay\Skills\Domain\Model\FileReference::class => [
        'tableName' => 'sys_file_reference',
        'properties' => [
            'originalFileIdentifier' => [
                'fieldName' => 'uid_local',
            ],
        ],
    ],
    \SkillDisplay\Skills\Domain\Model\GrantedReward::class => [
        'properties' => [
            'crdate' => [
                'fieldName' => 'crdate',
            ],
        ],
    ],
    \SkillDisplay\Skills\Domain\Model\Certification::class => [
        'properties' => [
            'crdate' => [
                'fieldName' => 'crdate',
            ],
        ],
    ],
    \SkillDisplay\Skills\Domain\Model\Brand::class => [
        'properties' => [
            'tstamp' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
    \SkillDisplay\Skills\Domain\Model\Link::class => [
        'properties' => [
            'tstamp' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
    \SkillDisplay\Skills\Domain\Model\Skill::class => [
        'properties' => [
            'tstamp' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
    \SkillDisplay\Skills\Domain\Model\SkillPath::class => [
        'properties' => [
            'tstamp' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
    \SkillDisplay\Skills\Domain\Model\Tag::class => [
        'properties' => [
            'tstamp' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
];
