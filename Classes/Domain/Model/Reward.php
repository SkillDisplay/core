<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Reward extends AbstractEntity
{
    public const TYPE_BADGE = 'badge';
    public const TYPE_CERTIFICATE = 'certificate';
    public const TYPE_AFFILIATE = 'affiliate';
    public const TYPE_DOWNLOAD = 'download';

    /** @var string */
    protected $title = '';

    /** @var string */
    protected $reward = '';

    /** @var string */
    protected $description = '';

    /** @var string */
    protected $detailLink = '';

    /** @var \TYPO3\CMS\Extbase\Domain\Model\Category|null */
    protected $category = null;

    /** @var \DateTime|null */
    protected $availabilityStart;

    /** @var \DateTime|null */
    protected $availabilityEnd;

    /** @var \DateTime */
    protected $validUntil;

    /** @var \SkillDisplay\Skills\Domain\Model\Brand|null */
    protected $validForOrganisation = null;

    /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\RewardPrerequisite> */
    protected $prerequisites = null;

    /** @var \SkillDisplay\Skills\Domain\Model\Brand */
    protected $brand;

    /** @var string */
    protected $type = '';

    /** @var int */
    protected $pdfLayoutFile = 0;

    /** @var int */
    protected $syllabusLayoutFile = 0;

    /** @var \SkillDisplay\Skills\Domain\Model\SkillPath|null */
    protected $skillpath;

    /** @var int */
    protected $level = 0;

    /** @var int */
    protected $active = 0;

    const ApiJsonViewConfiguration = [
        '_only' => [
            'uid', 'title', 'description', 'level', 'brand', 'skillpath'
        ],
        '_descend' => [
            'brand' => Brand::JsonViewMinimalConfiguration,
            'skillpath' => [
                '_only' => [
                    'uid', 'name'
                ]
            ]
        ]
    ];


    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getReward(): string
    {
        return $this->reward;
    }

    public function setReward(string $reward): void
    {
        $this->reward = $reward;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDetailLink(): string
    {
        return $this->detailLink;
    }

    public function setDetailLink(string $detailLink): void
    {
        $this->detailLink = $detailLink;
    }

    public function getAvailabilityStart(): ?\DateTime
    {
        return $this->availabilityStart;
    }

    public function setAvailabilityStart(\DateTime $availabilityStart = null): void
    {
        $this->availabilityStart = $availabilityStart;
    }

    public function getAvailabilityEnd(): ?\DateTime
    {
        return $this->availabilityEnd;
    }

    public function setAvailabilityEnd(\DateTime $availabilityEnd = null): void
    {
        $this->availabilityEnd = $availabilityEnd;
    }

    public function getValidUntil(): ?\DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(\DateTime $validUntil = null): void
    {
        $this->validUntil = $validUntil;
    }

    /**
     * @return RewardPrerequisite[]|\TYPO3\CMS\Extbase\Persistence\ObjectStorage
     */
    public function getPrerequisites(): \TYPO3\CMS\Extbase\Persistence\ObjectStorage
    {
        return $this->prerequisites;
    }

    public function setPrerequisites(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $prerequisites): void
    {
        $this->prerequisites = $prerequisites;
    }

    public function getBrand(): Brand
    {
        return $this->brand;
    }

    public function setBrand(Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getValidForOrganisation(): ?Brand
    {
        return $this->validForOrganisation;
    }

    public function setValidForOrganisation(Brand $validForOrganisation = null): void
    {
        $this->validForOrganisation = $validForOrganisation;
    }

    public function getPdfLayoutFile(): int
    {
        return $this->pdfLayoutFile;
    }

    public function setPdfLayoutFile(int $pdfLayoutFile): void
    {
        $this->pdfLayoutFile = $pdfLayoutFile;
    }

    public function getSyllabusLayoutFile(): int
    {
        return $this->syllabusLayoutFile;
    }

    public function setSyllabusLayoutFile(int $syllabusLayoutFile): void
    {
        $this->syllabusLayoutFile = $syllabusLayoutFile;
    }

    public function getSkillpath(): ?SkillPath
    {
        return $this->skillpath;
    }

    public function setSkillpath(SkillPath $skillpath): void
    {
        $this->skillpath = $skillpath;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level)
    {
        $this->level = $level;
    }

    /**
     * @return \TYPO3\CMS\Extbase\Domain\Model\Category|null
     */
    public function getCategory(): ?\TYPO3\CMS\Extbase\Domain\Model\Category
    {
        return $this->category;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Domain\Model\Category|null $category
     */
    public function setCategory(?\TYPO3\CMS\Extbase\Domain\Model\Category $category): void
    {
        $this->category = $category;
    }

    /**
     * @return int
     */
    public function getActive(): int
    {
        return $this->active;
    }

    /**
     * @param int $active
     */
    public function setActive(int $active): void
    {
        $this->active = $active;
    }



}
