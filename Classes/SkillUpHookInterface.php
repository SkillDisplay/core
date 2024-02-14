<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2016 Markus Klein, Reelworx GmbH
 **/

namespace SkillDisplay\Skills;

use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\User;

interface SkillUpHookInterface
{
    /**
     * Provides the HTML content of the ad-message
     *
     * @param Skill $skill
     * @param User $user
     * @param array $settings
     * @return string
     */
    public function getMessage(Skill $skill, User $user, array $settings): string;

    /**
     * Announces applicable actions and skills
     *
     * @return array Array of listeners with keys 'action' (tier1 to tier4) and 'skill' (uid of Skill)
     */
    public function getApplicableSkillActions(): array;
}
