<?php

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::makeCategorizable(
    'skills',
    'tx_skills_domain_model_reward',
    'category',
    [
        'fieldConfiguration' => [
            'maxitems' => 1,
            'size' => 5,
        ],
        'fieldList' => 'category',
        'position' => 'after:title'
    ],
    false
);
