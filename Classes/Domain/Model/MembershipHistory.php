<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Matthias Böhm <matthias.boehm@reelworx.at>, Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class MembershipHistory extends AbstractEntity
{
    /** @var \SkillDisplay\Skills\Domain\Model\Certification|null */
    protected $verification = null;

    /** @var \SkillDisplay\Skills\Domain\Model\Brand|null */
    protected $brand = null;

    /** @var string */
    protected $brandName = '';

    /**
     * @return Certification|null
     */
    public function getVerification(): ?Certification
    {
        return $this->verification;
    }

    /**
     * @param Certification|null $verification
     */
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

    /**
     * @param Brand|null $brand
     */
    public function setBrand(?Brand $brand): void
    {
        $this->brand = $brand;
    }

    /**
     * @return string
     */
    public function getBrandName(): string
    {
        return $this->brandName;
    }

    /**
     * @param string $brandName
     */
    public function setBrandName(string $brandName): void
    {
        $this->brandName = $brandName;
    }



}
