<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Domain\Model;

use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class OrganisationStatistics extends AbstractEntity
{
    public const JsonViewConfiguration = [
        '_only' => [
            'uid',
            'brand',
            'totalScore',
            'currentMonthUsers',
            'lastMonthUsers',
            'currentMonthVerifications',
            'lastMonthVerifications',
            'currentMonthIssued',
            'lastMonthIssued',
            'monthlyScores',
            'interestSets',
            'potential',
            'composition',
            'sumVerifications',
            'sumSupportedSkills',
            'sumSkills',
            'sumIssued',
        ],
        '_descend' => [
            'brand' => Brand::JsonViewMinimalConfiguration,
            'monthlyScores' => [],
            'interestSets' => [
                '_descendAll' => SkillPath::JsonViewConfiguration
            ],
            'potential' => [],
            'composition' => [],
        ]
    ];

    protected ?Brand $brand = null;
    protected int $totalScore = 0;
    protected int $currentMonthUsers = 0;
    protected int $lastMonthUsers = 0;
    protected int $currentMonthVerifications = 0;
    protected int $lastMonthVerifications = 0;
    protected int $currentMonthIssued = 0;
    protected int $lastMonthIssued = 0;
    protected string $monthlyScores = '';
    protected string $interests = '';
    protected string $potential = '';
    protected string $composition = '';
    protected int $sumVerifications = 0;
    protected int $sumSupportedSkills = 0;
    protected int $sumSkills = 0;
    protected int $sumIssued = 0;
    protected string $expertise = '';
    protected array $limitInterestToSkillSets = [];

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function getTotalScore(): int
    {
        return $this->totalScore;
    }

    public function setTotalScore(int $totalScore): void
    {
        $this->totalScore = $totalScore;
    }

    public function getCurrentMonthUsers(): int
    {
        return $this->currentMonthUsers;
    }

    public function setCurrentMonthUsers(int $currentMonthUsers): void
    {
        $this->currentMonthUsers = $currentMonthUsers;
    }

    public function getLastMonthUsers(): int
    {
        return $this->lastMonthUsers;
    }

    public function setLastMonthUsers(int $lastMonthUsers): void
    {
        $this->lastMonthUsers = $lastMonthUsers;
    }

    public function getCurrentMonthVerifications(): int
    {
        return $this->currentMonthVerifications;
    }

    public function setCurrentMonthVerifications(int $currentMonthVerifications): void
    {
        $this->currentMonthVerifications = $currentMonthVerifications;
    }

    public function getLastMonthVerifications(): int
    {
        return $this->lastMonthVerifications;
    }

    public function setLastMonthVerifications(int $lastMonthVerifications): void
    {
        $this->lastMonthVerifications = $lastMonthVerifications;
    }

    public function getCurrentMonthIssued(): int
    {
        return $this->currentMonthIssued;
    }

    public function setCurrentMonthIssued(int $currentMonthIssued): void
    {
        $this->currentMonthIssued = $currentMonthIssued;
    }

    public function getLastMonthIssued(): int
    {
        return $this->lastMonthIssued;
    }

    public function setLastMonthIssued(int $lastMonthIssued): void
    {
        $this->lastMonthIssued = $lastMonthIssued;
    }

    public function getMonthlyScores(): array
    {
        $data = (array)json_decode($this->monthlyScores, true);
        $firstMonth = key($data);
        $data = array_values($data);
        array_unshift($data, $firstMonth);
        return $data;
    }

    public function setMonthlyScores(string $monthlyScores): void
    {
        $this->monthlyScores = $monthlyScores;
    }

    public function getInterests(): array
    {
        return array_slice((array)json_decode($this->interests, true), 0, 5, true);
    }

    public function setInterests(string $interests): void
    {
        $this->interests = $interests;
    }

    public function getPotential(): array
    {
        return (array)json_decode($this->potential, true);
    }

    public function setPotential(string $potential): void
    {
        $this->potential = $potential;
    }

    public function getComposition(): array
    {
        /** @var ObjectManager $om */
        $om = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var SkillPathRepository $skillSetRepository */
        $brandRepository = $om->get(BrandRepository::class);

        $compositionWithBrandName = [];
        $composition = (array)json_decode($this->composition, true);
        foreach ($composition as $brandId => $tiers){
            $compositionWithBrandName[$brandRepository->findByUid($brandId)->getName()] = $tiers;
        }
        return $compositionWithBrandName;
    }

    public function setComposition(string $composition): void
    {
        $this->composition = $composition;
    }

    public function getSumVerifications(): int
    {
        return $this->sumVerifications;
    }

    public function getSumSupportedSkills(): int
    {
        return $this->sumSupportedSkills;
    }

    public function getSumSkills(): int
    {
        return $this->sumSkills;
    }

    public function getSumIssued(): int
    {
        return $this->sumIssued;
    }

    public function getExpertise(): array
    {
        return (array)json_decode($this->expertise, true);
    }

    public function setExpertise(string $expertise): void
    {
        $this->expertise = $expertise;
    }

    public function getInterestSets(): array
    {
        /** @var ObjectManager $om */
        $om = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var SkillPathRepository $skillSetRepository */
        $skillSetRepository = $om->get(SkillPathRepository::class);

        $skillSets = [];
        foreach ($this->getInterests() as $id => $count){
            if (empty($this->limitInterestToSkillSets) || array_search((int)$id, $this->limitInterestToSkillSets, true) !== false) {
                $skillSets[] = $skillSetRepository->findByUid($id);
            }
        }
        return $skillSets;
    }

    public function getLimitInterestToSkillSets(): array
    {
        return $this->limitInterestToSkillSets;
    }

    public function setLimitInterestToSkillSets(array $limitInterestToSkillSets): void
    {
        $limitSets = [];
        foreach ($limitInterestToSkillSets as $skillSet) {
            $limitSets[] = (int)$skillSet->getUid();
        }
        $this->limitInterestToSkillSets = $limitSets;
    }
}
