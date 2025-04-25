<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Domain\Model;

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 **/

use DateTime;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class InvitationCode extends AbstractEntity
{
    #[Validate(['validator' => 'NotEmpty'])]
    protected string $code = '';
    protected ?Brand $brand = null;
    protected ?DateTime $expires = null;
    protected ?User $createdBy = null;
    protected ?User $usedBy = null;
    protected ?DateTime $usedAt = null;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getBrand(): Brand
    {
        return $this->brand;
    }

    public function setBrand(Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function getExpires(): ?DateTime
    {
        return $this->expires;
    }

    public function setExpires(?DateTime $expires): void
    {
        $this->expires = $expires;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUsedBy(): ?User
    {
        return $this->usedBy;
    }

    public function setUsedBy(User $usedBy): void
    {
        $this->usedBy = $usedBy;
    }

    public function getUsedAt(): ?DateTime
    {
        return $this->usedAt;
    }

    public function setUsedAt(DateTime $usedAt): void
    {
        $this->usedAt = $usedAt;
    }
}
