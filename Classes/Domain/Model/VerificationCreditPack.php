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

use DateTime;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class VerificationCreditPack extends AbstractEntity
{
    public const array JsonViewConfiguration = [
        '_only' => [
            'uid',
            'title',
            'initialPoints',
            'currentPoints',
            'price',
            'valuta',
            'validThru',
        ],
        '_descend' => [
            'valuta' => [],
            'validThru' => [],
        ],
    ];

    protected string $title = '';
    protected ?DateTime $valuta = null;
    protected ?DateTime $validThru = null;

    protected ?Brand $brand = null;
    /** @var string duplicated name of brand */
    protected string $brandName = '';

    protected int $currentPoints = 0;
    protected int $initialPoints = 0;

    protected float $price = 0.0;
    protected string $invoiceNumber = '';

    /** @var User|null the user who created this pack (via API) */
    protected ?User $user = null;
    protected string $userUsername = '';
    protected string $userFirstname = '';
    protected string $userLastname = '';

    public function getValuta(): DateTime
    {
        return $this->valuta;
    }

    public function setValuta(DateTime $valuta): void
    {
        $this->valuta = $valuta;
    }

    public function getValidThru(): ?DateTime
    {
        return $this->validThru;
    }

    public function setValidThru(?DateTime $validThru): void
    {
        $this->validThru = $validThru;
    }

    public function getBrand(): Brand
    {
        return $this->brand;
    }

    public function setBrand(Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCurrentPoints(): int
    {
        return $this->currentPoints;
    }

    public function setCurrentPoints(int $currentPoints): void
    {
        $this->currentPoints = $currentPoints;
    }

    public function getInitialPoints(): int
    {
        return $this->initialPoints;
    }

    public function setInitialPoints(int $initialPoints): void
    {
        $this->initialPoints = $initialPoints;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(string $invoiceNumber): void
    {
        $this->invoiceNumber = $invoiceNumber;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function setBrandName(string $brandName): void
    {
        $this->brandName = $brandName;
    }

    public function getUserUsername(): string
    {
        return $this->userUsername;
    }

    public function setUserUsername(string $userUsername): void
    {
        $this->userUsername = $userUsername;
    }

    public function getUserFirstname(): string
    {
        return $this->userFirstname;
    }

    public function setUserFirstname(string $userFirstname): void
    {
        $this->userFirstname = $userFirstname;
    }

    public function getUserLastname(): string
    {
        return $this->userLastname;
    }

    public function setUserLastname(string $userLastname): void
    {
        $this->userLastname = $userLastname;
    }
}
