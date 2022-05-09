<?php
defined('TYPO3_MODE') || die('Access denied.');

$iconReg = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconReg->registerIcon(
    'skills-skill-type-default',
    \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
    ['source' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_skill.png']
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule('skilldisplay', '', 'after:web', null, [
    'icon' => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
    'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_mainmodule.xlf',
]);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'Skills',
    'skilldisplay', // Make module a submodule of 'web'
    'vse', // Submodule key
    '', // Position
    [
        \SkillDisplay\Skills\Controller\BackendVseController::class => 'skillTree',
        \SkillDisplay\Skills\Controller\BackendController::class => 'syllabusForSet, completeDownloadForSet',
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_vse.xlf',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'Skills',
    'skilldisplay', // Make module a submodule of 'web'
    'verifier', // Submodule key
    '', // Position
    [
        \SkillDisplay\Skills\Controller\BackendVerifierController::class => 'verifierPermissions,modifyPermissions,addVerifier',
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_verifier.xlf',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'Skills',
    'skilldisplay', // Make module a submodule of 'web'
    'verificationmanager', // Submodule key
    '', // Position
    [
        \SkillDisplay\Skills\Controller\BackendVerificationManagerController::class => 'creditOverview, creditHistory, verificationManager, generateCSV',
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_verificationmanager.xlf',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'Skills',
    'skilldisplay', // Make module a submodule of 'web'
    'skills', // Submodule key
    '', // Position
    [
        \SkillDisplay\Skills\Controller\BackendController::class => 'skillUpSplitting,moveCertifications,syllabus,syllabusForSet,completeDownload,reporting,generateReport,completeDownloadForSet'
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be.xlf',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
    ]
);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'Skills',
    'skilldisplay', // Make module a submodule of 'web'
    'awardsmanager', // Submodule key
    '', // Position
    [
        \SkillDisplay\Skills\Controller\BackendAwardsManagerController::class => 'awardsManager,skillSetAwards,toggleAwardActivation,createNewAward'
    ],
    [
        'access' => 'user,group',
        'icon'   => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_awardsmanager.xlf',
        'navigationComponentId' => '',
        'inheritNavigationComponentFromMainModule' => false,
    ]
);
