<?php

declare(strict_types=1);

use SkillDisplay\Skills\Controller\BackendAwardsManagerController;
use SkillDisplay\Skills\Controller\BackendController;
use SkillDisplay\Skills\Controller\BackendVerificationManagerController;
use SkillDisplay\Skills\Controller\BackendVerifierController;
use SkillDisplay\Skills\Controller\BackendVseController;

return [
    'skilldisplay' => [
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_mainmodule.xlf',
        'iconIdentifier' => 'skills-be-main-module',
        'workspaces' => 'live',
        'position' => [
            'after' => 'web',
        ],
    ],
    'skilldisplay_SkillsVse' => [
        'parent' => 'skilldisplay',
        'access' => 'user',
        'iconIdentifier' => 'skills-be-main-module',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_vse.xlf',
        'extensionName' => 'Skills',
        'controllerActions' => [
            BackendVseController::class => ['skillTree'],
            BackendController::class => ['syllabusForSet', 'completeDownloadForSet'],
        ],
    ],
    'skilldisplay_SkillsVerifier' => [
        'parent' => 'skilldisplay',
        'access' => 'user',
        'iconIdentifier' => 'skills-be-main-module',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_verifier.xlf',
        'extensionName' => 'Skills',
        'controllerActions' => [
            BackendVerifierController::class => ['verifierPermissions', 'modifyPermissions', 'addVerifier'],
        ],
    ],
    'verificationmanager' => [
        'parent' => 'skilldisplay',
        'access' => 'user',
        'iconIdentifier' => 'skills-be-main-module',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_verificationmanager.xlf',
        'extensionName' => 'Skills',
        'controllerActions' => [
            BackendVerificationManagerController::class => ['creditOverview', 'creditHistory', 'verificationManager', 'generateCSV'],
        ],
    ],
    'skills' => [
        'parent' => 'skilldisplay',
        'access' => 'user',
        'iconIdentifier' => 'skills-be-main-module',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be.xlf',
        'extensionName' => 'Skills',
        'controllerActions' => [
            BackendController::class => ['skillUpSplitting', 'moveCertifications', 'syllabus', 'syllabusForSet', 'completeDownload', 'reporting', 'generateReport', 'completeDownloadForSet'],
        ],
    ],
    'awardsmanager' => [
        'parent' => 'skilldisplay',
        'access' => 'user',
        'iconIdentifier' => 'skills-be-main-module',
        'labels' => 'LLL:EXT:skills/Resources/Private/Language/locallang_be_awardsmanager.xlf',
        'extensionName' => 'Skills',
        'controllerActions' => [
            BackendAwardsManagerController::class => ['awardsManager', 'skillSetAwards', 'toggleAwardActivation', 'createNewAward'],
        ],
    ],
];
