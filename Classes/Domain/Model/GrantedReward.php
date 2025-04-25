<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Model;

use DateTime;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class GrantedReward extends AbstractEntity
{
    protected int $crdate = 0;
    protected ?Reward $reward = null;
    protected ?User $user = null;
    protected ?DateTime $validUntil = null;
    protected bool $selectedByUser = false;
    protected int $positionIndex = 0;

    public const array ApiJsonViewConfiguration = [
        '_only' => [
            'uid', 'positionIndex', 'reward',
        ],
        '_descend' => [
            'reward' => Reward::ApiJsonViewConfiguration,
        ],
    ];

    public function getSelectedByUser(): bool
    {
        return $this->selectedByUser;
    }

    public function setSelectedByUser(bool $selectedByUser): void
    {
        $this->selectedByUser = $selectedByUser;
    }

    public function getReward(): ?Reward
    {
        return $this->reward;
    }

    public function setReward(Reward $reward): void
    {
        $this->reward = $reward;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getValidUntil(): ?DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(?DateTime $validUntil = null): void
    {
        $this->validUntil = $validUntil;
    }

    public function getCrdate(): int
    {
        return $this->crdate;
    }

    public function setCrdate(int $crdate): void
    {
        $this->crdate = $crdate;
    }

    public function getPositionIndex(): int
    {
        return $this->positionIndex;
    }

    public function setPositionIndex(int $positionIndex): void
    {
        $this->positionIndex = $positionIndex;
    }
}
