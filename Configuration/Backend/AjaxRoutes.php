<?php

use SkillDisplay\Skills\Controller\BackendController;
use SkillDisplay\Skills\Controller\BackendVseController;

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
        'target' => BackendVseController::class . '::ajaxTreeSources',
    ],
    'treeData' => [
        'path' => '/skills/treeData',
        'target' => BackendController::class . '::ajaxTreeData',
    ],
    'addSkill' => [
        'path' => '/skills/addSkill',
        'target' => BackendController::class . '::ajaxAddSkill',
    ],
    'addLink' => [
        'path' => '/skills/addLink',
        'target' => BackendController::class . '::ajaxAddLink',
    ],
    'deleteSkill' => [
        'path' => '/skills/deleteSkill',
        'target' => BackendController::class . '::ajaxDeleteSkill',
    ],
    'setSkillDormant' => [
        'path' => '/skills/setSkillDormant',
        'target' => BackendController::class . '::ajaxSetSkillDormant',
    ],
    'removeSkillFromReward' => [
        'path' => '/skills/removeSkillFromReward',
        'target' => BackendController::class . '::ajaxRemoveSkillFromReward',
    ],
    'removeRequirement' => [
        'path' => '/skills/removeRequirement',
        'target' => BackendController::class . '::ajaxRemoveRequirement',
    ],
];
