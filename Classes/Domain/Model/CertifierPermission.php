<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class CertifierPermission extends AbstractEntity
{
    protected bool $tier1 = false;
    protected bool $tier2 = false;
    protected bool $tier4 = false;
    protected ?Skill $skill = null;

    public function getTier1(): bool
    {
        return $this->tier1;
    }

    public function setTier1(bool $tier1): void
    {
        $this->tier1 = $tier1;
    }

    public function isTier1(): bool
    {
        return $this->tier1;
    }

    public function getTier2(): bool
    {
        return $this->tier2;
    }

    public function setTier2($tier2): void
    {
        $this->tier2 = $tier2;
    }

    public function isTier2(): bool
    {
        return $this->tier2;
    }

    public function getTier4(): bool
    {
        return $this->tier4;
    }

    public function setTier4($tier4): void
    {
        $this->tier4 = $tier4;
    }

    public function isTier4(): bool
    {
        return $this->tier4;
    }

    public function getSkill(): ?Skill
    {
        return $this->skill;
    }

    public function setSkill(Skill $skill): void
    {
        $this->skill = $skill;
    }
}
