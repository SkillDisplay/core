<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Award extends AbstractEntity
{
    public const array RANK_STRINGS = [
        3 => 'Platinum',
        2 => 'Gold',
        1 => 'Silver',
        0 => 'Bronze',
    ];

    public const int TYPE_VERIFICATIONS = 0;
    public const int TYPE_MEMBER = 1;
    public const int TYPE_COACH = 2;
    public const int TYPE_MENTOR = 3;

    public const array JsonViewConfiguration = [
        '_only' => [
            'uid', 'brand', 'user', 'type', 'level', 'rank',
        ],
    ];

    public const array ApiJsonViewConfiguration = [
        '_only' => [
            'uid', 'title', 'brand', 'rank', 'level',
        ],
        '_descend' => [
            'brand' => Brand::JsonViewMinimalConfiguration,
        ],
    ];

    protected ?Brand $brand = null;
    protected ?User $user = null;
    protected int $type = 0;
    protected int $level = 0;
    protected int $rank = 0;

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function setRank(int $rank): void
    {
        $this->rank = $rank;
    }

    public function getBrand(): Brand
    {
        return $this->brand;
    }

    public function setBrand(Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getTitle(): string
    {
        $title = '';
        switch ($this->getRank()) {
            case 0: {
                $title = $title . self::RANK_STRINGS[0];
                break;
            }
            case 1: {
                $title = $title . self::RANK_STRINGS[1];
                break;
            }
            case 2: {
                $title = $title . self::RANK_STRINGS[2];
                break;
            }
            case 3: {
                $title = $title . self::RANK_STRINGS[3];
                break;
            }
        }

        $title = $title . ' ';

        switch ($this->getType()) {
            case 0: {
                switch ($this->getLevel()) {
                    case 1: {
                        $title = $title . 'Certified';
                        break;
                    }
                    case 2: {
                        $title = $title . 'Student';
                        break;
                    }
                    case 3: {
                        $title = $title . 'Learner';
                        break;
                    }
                    case 4: {
                        $title = $title . 'Artisan';
                        break;
                    }
                }
                break;
            }
            case 1: {
                $title = $title . 'Member';
                break;
            }
            case 2: {
                $title = $title . 'Coach';
                break;
            }
            case 3: {
                $title = $title . 'Mentor';
                break;
            }
        }
        return $title;
    }
}
