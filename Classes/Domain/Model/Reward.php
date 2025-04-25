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
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Reward extends AbstractEntity
{
    public const string TYPE_BADGE = 'badge';
    public const string TYPE_CERTIFICATE = 'certificate';
    public const string TYPE_AFFILIATE = 'affiliate';
    public const string TYPE_DOWNLOAD = 'download';

    protected string $title = '';
    protected string $reward = '';
    protected string $description = '';
    protected string $detailLink = '';
    protected ?Category $category = null;
    protected ?DateTime $availabilityStart = null;
    protected ?DateTime $availabilityEnd = null;
    protected ?DateTime $validUntil = null;
    protected ?Brand $validForOrganisation = null;
    /** @var ObjectStorage<RewardPrerequisite> */
    protected ObjectStorage $prerequisites;
    protected ?Brand $brand = null;
    protected string $type = '';
    protected int $pdfLayoutFile = 0;
    protected int $syllabusLayoutFile = 0;
    protected ?SkillPath $skillpath = null;
    protected int $level = 0;
    protected int $active = 0;
    protected bool $linkSkillpath = true;

    public const array ApiJsonViewConfiguration = [
        '_only' => [
            'uid', 'title', 'description', 'level', 'brand', 'skillpath', 'linkSkillpath',
        ],
        '_descend' => [
            'brand' => Brand::JsonViewMinimalConfiguration,
            'skillpath' => [
                '_only' => [
                    'uid', 'name',
                ],
            ],
        ],
    ];

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->prerequisites = new ObjectStorage();
    }

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

    public function getAvailabilityStart(): ?DateTime
    {
        return $this->availabilityStart;
    }

    public function setAvailabilityStart(?DateTime $availabilityStart = null): void
    {
        $this->availabilityStart = $availabilityStart;
    }

    public function getAvailabilityEnd(): ?DateTime
    {
        return $this->availabilityEnd;
    }

    public function setAvailabilityEnd(?DateTime $availabilityEnd = null): void
    {
        $this->availabilityEnd = $availabilityEnd;
    }

    public function getValidUntil(): ?DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(?DateTime $validUntil = null): void
    {
        $this->validUntil = $validUntil;
    }

    /**
     * @return ObjectStorage<RewardPrerequisite>
     */
    public function getPrerequisites(): ObjectStorage
    {
        return $this->prerequisites;
    }

    public function setPrerequisites(ObjectStorage $prerequisites): void
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

    public function setValidForOrganisation(?Brand $validForOrganisation = null): void
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

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    /**
     * @return Category|null
     */
    public function getCategory(): ?Category
    {
        return $this->category;
    }

    /**
     * @param Category|null $category
     */
    public function setCategory(?Category $category): void
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

    /**
     * @return bool
     */
    public function isLinkSkillpath(): bool
    {
        return $this->linkSkillpath;
    }

    /**
     * @param bool $linkSkillpath
     */
    public function setLinkSkillpath(bool $linkSkillpath): void
    {
        $this->linkSkillpath = $linkSkillpath;
    }

}
