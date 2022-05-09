<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class RewardPrerequisite extends AbstractEntity
{
    /** @var \SkillDisplay\Skills\Domain\Model\Reward */
    protected $reward;

    /** @var \SkillDisplay\Skills\Domain\Model\Skill */
    protected $skill;

    /** @var int */
    protected $level = 0;

    /** @var \SkillDisplay\Skills\Domain\Model\Brand */
    protected $brand;

    public function getReward(): Reward
    {
        return $this->reward;
    }

    public function setReward(Reward $reward): void
    {
        $this->reward = $reward;
    }

    public function getSkill(): Skill
    {
        return $this->skill;
    }

    public function setSkill(Skill $skill): void
    {
        $this->skill = $skill;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(Brand $brand): void
    {
        $this->brand = $brand;
    }
}
