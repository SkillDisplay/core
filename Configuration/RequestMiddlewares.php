<?php

use SkillDisplay\Skills\Middleware\ApiRouter;
use SkillDisplay\Skills\Middleware\OptionMethod;

return [
    'frontend' => [
        'api' => [
            'target' => ApiRouter::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/eid',
            ],
        ],
        'http-options' => [
            'target' => OptionMethod::class,
            'before' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
        ],
    ],
];
