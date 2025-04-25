<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

$iconConfig = [
    'skills-skill-type-default' => [
        'provider' => BitmapIconProvider::class,
        'source' => 'EXT:skills/Resources/Public/Icons/tx_skills_domain_model_skill.png',
    ],
    'skills-be-main-module' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:skills/Resources/Public/Images/SkillDisplay_Logo_whitebg.svg',
    ],
];

$pluginIconList = [
    'skills-organisations' => 'Extension.png',
    'skills-skills' => 'Extension.png',
    'skills-user-register' => 'Extension.png',
    'skills-user-edit' => 'Extension.png',
    'skills-short-link' => 'Extension.png',
    'skills-routing' => 'Extension.png',
    'skills-api' => 'Extension.png',
    'skills-anonymous' => 'Extension.png',
];

foreach ($pluginIconList as $key => $path) {
    $iconConfig[$key] = [
        'provider' => BitmapIconProvider::class,
        'source' => 'EXT:skills/Resources/Public/Icons/' . $path,
    ];
}

return $iconConfig;
