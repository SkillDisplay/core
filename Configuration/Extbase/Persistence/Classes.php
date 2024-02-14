<?php

declare(strict_types=1);

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\FileReference;
use SkillDisplay\Skills\Domain\Model\GrantedReward;
use SkillDisplay\Skills\Domain\Model\Link;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\Tag;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;

return [
    FrontendUser::class => [
        'subclasses' => [
            'Tx_Skills_User' => User::class,
        ],
    ],
    User::class => [
        'tableName' => 'fe_users',
        'recordType' => 'Tx_Skills_User',
    ],
    FileReference::class => [
        'tableName' => 'sys_file_reference',
        'properties' => [
            'originalFileIdentifier' => [
                'fieldName' => 'uid_local',
            ],
        ],
    ],
    GrantedReward::class => [
        'properties' => [
            'crdate' => [
                'fieldName' => 'crdate',
            ],
        ],
    ],
    Certification::class => [
        'properties' => [
            'crdate' => [
                'fieldName' => 'crdate',
            ],
        ],
    ],
    Brand::class => [
        'properties' => [
            'tstamp' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
    Link::class => [
        'properties' => [
            'tstamp' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
    Skill::class => [
        'properties' => [
            'tstamp' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
    SkillPath::class => [
        'properties' => [
            'tstamp' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
    Tag::class => [
        'properties' => [
            'tstamp' => [
                'fieldName' => 'tstamp',
            ],
        ],
    ],
];
