<?php

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::makeCategorizable(
    'skills',
    'tx_skills_domain_model_skillpath',
    'categories',
    [
        'fieldConfiguration' => [
            'minitems' => 1,
            'maxitems' => 1,
            'size' => 5,
        ],
        'fieldList' => 'categories',
        'position' => 'before:description'
    ],
    false
);
