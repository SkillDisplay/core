<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class VerificationCreditUsage extends AbstractEntity
{
    protected ?VerificationCreditPack $creditPack = null;
    protected ?Certification $verification = null;
    protected int $points = 0;
    protected float $price = 0.0;

    public function getCreditPack(): ?VerificationCreditPack
    {
        return $this->creditPack;
    }

    public function setCreditPack(VerificationCreditPack $creditPack): void
    {
        $this->creditPack = $creditPack;
    }

    public function getVerification(): ?Certification
    {
        return $this->verification;
    }

    public function setVerification(Certification $verification): void
    {
        $this->verification = $verification;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }
}
