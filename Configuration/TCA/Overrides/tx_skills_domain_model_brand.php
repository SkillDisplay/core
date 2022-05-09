<?php

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::makeCategorizable(
    'skills',
    'tx_skills_domain_model_brand',
    'categories',
    [
        'fieldConfiguration' => [
            'maxitems' => 1,
            'size' => 5,
        ],
        'fieldList' => 'categories',
        'position' => 'after:name'
    ],
    false
);
