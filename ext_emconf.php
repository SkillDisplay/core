<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'SkillDisplay Skill Management backend extension',
    'description' => 'The extension provides the backend functionality for the MySkillDisplay app',
    'category' => 'plugin',
    'author' => 'SkillDisplay',
    'author_email' => 'support@skilldisplay.eu',
    'state' => 'stable',
    'version' => '11.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.3.99',
            'typo3' => '11.5.33-11.5.99',
            'pdfviewhelpers' => '3.0.0-3.99.99',
            'static_info_tables' => '11.0.0-11.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'news' => '10.0.0-11.99.99',
        ],
    ],
];
