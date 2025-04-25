<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Domain\Model;

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 **/

use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;

class CertificationCredit extends AbstractEntity
{
    protected int $valuta = 0;
    protected ?Brand $brand = null;
    protected string $title = '';
    protected float $price = 0.0;

    #[Lazy]
    protected User|LazyLoadingProxy|null $user = null;

    protected string $invoiceNumber = '';
    protected string $brandName = '';
    protected string $userUsername = '';
    protected string $userFirstname = '';
    protected string $userLastName = '';

    public function getValuta(): int
    {
        return $this->valuta;
    }

    public function setValuta(int $valuta): void
    {
        $this->valuta = $valuta;
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
        if ($this->user instanceof LazyLoadingProxy) {
            $this->user = $this->user->_loadRealInstance();
        }
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

    public function getUserLastName(): string
    {
        return $this->userLastName;
    }

    public function setUserLastName(string $userLastName): void
    {
        $this->userLastName = $userLastName;
    }
}
