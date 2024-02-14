<?php

defined('TYPO3') || die('Access denied.');

use SkillDisplay\Skills\Controller\BackendAwardsManagerController;
use SkillDisplay\Skills\Controller\BackendController;
use SkillDisplay\Skills\Controller\BackendVerificationManagerController;
use SkillDisplay\Skills\Controller\BackendVerifierController;
use SkillDisplay\Skills\Controller\BackendVseController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionManagementUtility::addModule('skilldisplay', '', 'after:web', null, [
    'icon' => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
    'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_mainmodule.xlf',
]);

ExtensionUtility::registerModule(
    'Skills',
    'skilldisplay', // Make module a submodule of 'web'
    'vse', // Submodule key
    '', // Position
    [
        BackendVseController::class => 'skillTree',
        BackendController::class => 'syllabusForSet, completeDownloadForSet',
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_vse.xlf',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
    ]
);

ExtensionUtility::registerModule(
    'Skills',
    'skilldisplay', // Make module a submodule of 'web'
    'verifier', // Submodule key
    '', // Position
    [
        BackendVerifierController::class => 'verifierPermissions,modifyPermissions,addVerifier',
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_verifier.xlf',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
    ]
);

ExtensionUtility::registerModule(
    'Skills',
    'skilldisplay', // Make module a submodule of 'web'
    'verificationmanager', // Submodule key
    '', // Position
    [
        BackendVerificationManagerController::class => 'creditOverview, creditHistory, verificationManager, generateCSV',
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_verificationmanager.xlf',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
    ]
);

ExtensionUtility::registerModule(
    'Skills',
    'skilldisplay', // Make module a submodule of 'web'
    'skills', // Submodule key
    '', // Position
    [
        BackendController::class => 'skillUpSplitting,moveCertifications,syllabus,syllabusForSet,completeDownload,reporting,generateReport,completeDownloadForSet',
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be.xlf',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
    ]
);

ExtensionUtility::registerModule(
    'Skills',
    'skilldisplay', // Make module a submodule of 'web'
    'awardsmanager', // Submodule key
    '', // Position
    [
        BackendAwardsManagerController::class => 'awardsManager,skillSetAwards,toggleAwardActivation,createNewAward',
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_awardsmanager.xlf',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
    ]
);
