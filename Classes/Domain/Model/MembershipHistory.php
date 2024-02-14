<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Matthias Böhm <matthias.boehm@reelworx.at>, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class MembershipHistory extends AbstractEntity
{
    protected ?Certification $verification = null;
    protected ?Brand $brand = null;
    protected string $brandName = '';

    public function getVerification(): ?Certification
    {
        return $this->verification;
    }

    public function setVerification(?Certification $verification): void
    {
        $this->verification = $verification;
    }

    /**
     * @return Brand|null
     */
    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function setBrandName(string $brandName): void
    {
        $this->brandName = $brandName;
    }
}
