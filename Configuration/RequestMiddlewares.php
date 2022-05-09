<?php
return [
    'frontend' => [
        'api' => [
            'target' => \SkillDisplay\Skills\Middleware\ApiRouter::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/eid'
            ]
        ],
        'http-options' => [
            'target' => \SkillDisplay\Skills\Middleware\OptionMethod::class,
            'before' => [
                'typo3/cms-core/normalized-params-attribute',
            ]
        ],
    ],
];
