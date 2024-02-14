<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Domain\Model;

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Reelworx GmbH
 **/

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Campaign extends AbstractEntity
{
    public const JsonViewConfiguration = [
        '_only' => [
            'uid',
            'title',
        ],
    ];

    protected string $title = '';

    /**
     * Certified user
     *
     * @var User|null
     */
    protected ?User $user = null;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function __toString()
    {
        return $this->title;
    }
}
