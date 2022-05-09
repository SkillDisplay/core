<?php

/**
 * Definitions for routes provided by EXT:backend
 * Contains all AJAX-based routes for entry points
 *
 * Currently the "access" property is only used so no token creation + validation is made
 * but will be extended further.
 */
return [
    'treeSources' => [
        'path' => '/skills/treeSources',
        'target' => \SkillDisplay\Skills\Controller\BackendVseController::class . '::ajaxTreeSources'
    ],
    'treeData' => [
        'path' => '/skills/treeData',
        'target' => \SkillDisplay\Skills\Controller\BackendController::class . '::ajaxTreeData'
    ],
    'addSkill' => [
        'path' => '/skills/addSkill',
        'target' => \SkillDisplay\Skills\Controller\BackendController::class . '::ajaxAddSkill'
    ],
    'addLink' => [
        'path' => '/skills/addLink',
        'target' => \SkillDisplay\Skills\Controller\BackendController::class . '::ajaxAddLink'
    ],
    'deleteSkill' => [
        'path' => '/skills/deleteSkill',
        'target' => \SkillDisplay\Skills\Controller\BackendController::class . '::ajaxDeleteSkill'
    ],
    'setSkillDormant' => [
        'path' => '/skills/setSkillDormant',
        'target' => \SkillDisplay\Skills\Controller\BackendController::class . '::ajaxSetSkillDormant'
    ],
    'removeSkillFromReward' => [
        'path' => '/skills/removeSkillFromReward',
        'target' => \SkillDisplay\Skills\Controller\BackendController::class . '::ajaxRemoveSkillFromReward'
    ],
    'removeRequirement' => [
        'path' => '/skills/removeRequirement',
        'target' => \SkillDisplay\Skills\Controller\BackendController::class . '::ajaxRemoveRequirement'
    ]
];
