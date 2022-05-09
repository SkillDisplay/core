<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'SkillDisplay Skill Management backend extension',
    'description' => 'The extension provides the backend functionality for the MySkillDisplay app',
    'category' => 'plugin',
    'author' => 'SkillDisplay',
    'author_email' => 'support@skilldisplay.eu',
    'state' => 'stable',
    'clearCacheOnLoad' => true,
    'version' => '3.0.0',
    'constraints' => [
        'depends' => [
            'php' => '7.4.0-7.4.99',
            'typo3' => '10.4.2-10.4.99',
            'pdfviewhelpers' => '2.0.0-2.99.99',
            'static_info_tables' => '6.8.0-6.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'news' => '8.2.0-8.99.99',
        ],
    ],
];
