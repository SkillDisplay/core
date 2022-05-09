<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein
 *
 ***/

namespace SkillDisplay\Skills\Domain\Model;

use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Service\CertoBot;
use SkillDisplay\Skills\Service\Importer\ExportService;
use SkillDisplay\Skills\Service\VerificationService;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class SkillPath extends AbstractEntity
{
    public const JsonViewConfiguration = [
        '_only' => [
            'uid',
            'name',
            'skillCount',
            'mediaPublicUrl',
            'progressPercentage',
            'legitimationDate',
            'firstCategoryTitle',
        ],
        '_descend' => [
            'progressPercentage' => [],
        ],
    ];

    public const JsonRecommendedViewConfiguration = [
        '_only' => [
            'uid',
            'name',
            'description',
            'mediaPublicUrl',
            'brand',
            'skillCount',
            'firstCategoryTitle',
        ],
        '_descend' => [
            'brand' => Brand::JsonViewMinimalConfiguration,
        ],
    ];

    public const TRANSLATE_FIELDS = [
        'name',
        'description',
    ];

    public const VISIBILITY_PUBLIC = 0;
    public const VISIBILITY_ORGANISATION = 1;
    public const VISIBILITY_LINK = 2;

    /**
     * @var string
     * @TYPO3\CMS\Extbase\Annotation\Validate("NotEmpty")
     */
    protected $name = '';

    /**
     * @var string
     * @TYPO3\CMS\Extbase\Annotation\Validate("NotEmpty")
     */
    protected $description = '';

    /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Brand> */
    protected $brands = null;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference>
     * @TYPO3\CMS\Extbase\Annotation\ORM\Cascade("remove")
     */
    protected $media = null;

    /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Skill> */
    protected $skills = null;

    /** @var \SkillDisplay\Skills\Domain\Model\User */
    protected $user = null;

    /** @var \SkillDisplay\Skills\Domain\Model\User */
    protected $legitimationUser = null;

    /** @var int */
    protected $legitimationDate = 0;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Link>
     * @TYPO3\CMS\Extbase\Annotation\ORM\Cascade("remove")
     */
    protected $links = null;

    /** @var CertificationRepository */
    protected $certificationRepository = null;

    /** @var int */
    protected $tstamp = 0;

    /** @var string */
    protected $uuid = '';

    /** @var int */
    protected $imported = 0;

    /** @var int */
    protected $syllabusLayoutFile = 0;

    /** @var string */
    protected $certificateLink = '';

    /** @var int */
    protected $certificateLayoutFile = 0;

    /** @var int */
    protected $visibility = 0;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\Category>
     */
    protected $categories = null;

    /**
     * non-persisted property
     * @var array
     */
    protected array $recommendedSkillSets = [];

    protected float $popularityLog2 = 0.0;

    public function __construct()
    {
        $this->uuid = CertoBot::uuid();
        $this->brands = new ObjectStorage();
        $this->skills = new ObjectStorage();
        $this->media = new ObjectStorage();
        $this->links = new ObjectStorage();
    }

    public function injectCertificationRepository(CertificationRepository $certificationRepository)
    {
        $this->certificationRepository = $certificationRepository;
    }

    public function setUserForCompletedChecks(User $user): void
    {
        $this->user = $user;
        /** @var Skill $skill */
        foreach ($this->getSkills() as $skill) {
            $skill->setUserForCompletedChecks($user);
        }
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Returns the skills
     *
     * @return ObjectStorage<\SkillDisplay\Skills\Domain\Model\Skill>
     */
    public function getSkills(): ObjectStorage
    {
        return $this->skills;
    }

    /**
     * Sets the skills
     *
     * @param ObjectStorage<\SkillDisplay\Skills\Domain\Model\Skill> $skills
     * @return void
     */
    public function setSkills(ObjectStorage $skills): void
    {
        $this->skills = $skills;
    }

    public function getSkillGroupId(): string
    {
        return 'skillPath-' . $this->getUid() . '-' . uniqid('path');
    }

    public function getVerifiers(): array
    {
        $brandRepo = GeneralUtility::makeInstance(ObjectManager::class)->get(BrandRepository::class);
        return $brandRepo->findVerifierBrandsForPath($this, 0);
    }

    public function getCertifiers(): array
    {
        $brandRepo = GeneralUtility::makeInstance(ObjectManager::class)->get(BrandRepository::class);
        return $brandRepo->findVerifierBrandsForPath($this, 1);
    }

    public function getSkillIds(): array
    {
        $skillIds = [];
        foreach ($this->skills as $skill) {
            $skillIds[] = $skill->getUid();
        }
        return $skillIds;
    }

    private function getCompletedInformation(): CertificationStatistics
    {
        $certStats = new CertificationStatistics();
        if (!$this->user) {
            $certStats->seal();
            return $certStats;
        }
        $cacheKey = 'completed_' . $this->user->getUid() . '_' . $this->getUid();
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('skillset_progress');
        $cachedStats = $cache->get($cacheKey);
        if ($cachedStats) {
            return $cachedStats;
        }
        $certifications = [];
        if(!empty($this->skills->toArray())) {
            $certifications = $this->certificationRepository->findBySkillsAndUser($this->skills->toArray(), $this->user);
        }

        foreach ($certifications as $cert) {
            $certStats->addCertification($cert);
        }
        // check if all skills are granted of the path, so the path itself is granted
        $certStats->removeVerificationsNotMatchingNumber('granted', $this->skills->count());
        $certStats->removeNonGroupRequests('pending', $this->uid);
        $certStats->seal();

        $cacheTags = ['tx_skills_domain_model_skillpath_' . $this->getUid()];
        /** @var Skill $skill */
        foreach ($this->skills as $skill) {
            $cacheTags[] = $skill->getCacheTag($this->user->getUid());
        }
        $cache->set($cacheKey, $certStats, $cacheTags);

        return $certStats;
    }

    /**
     * @return float[]
     */
    public function getProgressPercentage(): array
    {
        $stats = [
            'tier3' => 0,
            'tier2' => 0,
            'tier1' => 0,
            'tier4' => 0,
        ];
        $skillCount = $this->skills->count();
        if (!$skillCount || !$this->user) {
            return $stats;
        }
        $cacheKey = 'percent_' . $this->user->getUid() . '_' . $this->getUid();
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('skillset_progress');
        $cachedStats = $cache->get($cacheKey);
        if ($cachedStats) {
            return $cachedStats;
        }

        $cacheTags = [];
        /** @var Skill $skill */
        foreach ($this->skills as $skill) {
            $stat = $skill->getSingleProgressPercentage();
            $stats = [
                'tier3' => $stats['tier3'] + $stat['tier3'],
                'tier2' => $stats['tier2'] + $stat['tier2'],
                'tier1' => $stats['tier1'] + $stat['tier1'],
                'tier4' => $stats['tier4'] + $stat['tier4'],
            ];
            $cacheTags[] = $skill->getCacheTag($this->user->getUid());
        }

        $stats = [
            'tier3' => $stats['tier3'] / $skillCount,
            'tier2' => $stats['tier2'] / $skillCount,
            'tier1' => $stats['tier1'] / $skillCount,
            'tier4' => $stats['tier4'] / $skillCount,
        ];
        $cacheTags[] = 'tx_skills_domain_model_skillpath_' . $this->getUid();
        $cache->set($cacheKey, $stats, $cacheTags);
        return $stats;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function addBrand(Brand $brand): void
    {
        $this->brands->attach($brand);
    }

    public function removeBrand(Brand $brandToRemove): void
    {
        $this->brands->detach($brandToRemove);
    }

    /**
     * Returns the brands
     *
     * @return ObjectStorage<\SkillDisplay\Skills\Domain\Model\Brand>
     */
    public function getBrands()
    {
        return $this->brands;
    }

    public function getBrand(): ?Brand
    {
        $this->brands->rewind();
        if (!$this->brands->valid()) {
            return null;
        }
        return $this->brands->current();
    }

    /**
     * Sets the brands
     *
     * @param ObjectStorage<\SkillDisplay\Skills\Domain\Model\Brand> $brands
     * @return void
     */
    public function setBrands(ObjectStorage $brands): void
    {
        $this->brands = $brands;
    }

    public function addSkill(Skill $skill): void
    {
        $this->skills->attach($skill);
    }

    public function removeSkill(Skill $skillToRemove): void
    {
        $this->skills->detach($skillToRemove);
    }

    public function getSkillCount(): int
    {
        return $this->skills->count();
    }

    public function addMedia(\TYPO3\CMS\Extbase\Domain\Model\FileReference $media): void
    {
        $this->media->attach($media);
    }

    public function removeMedia(\TYPO3\CMS\Extbase\Domain\Model\FileReference $mediaToRemove): void
    {
        $this->media->detach($mediaToRemove);
    }

    /**
     * Returns the media
     *
     * @return ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference>
     */
    public function getMedia()
    {
        return $this->media;
    }

    /**
     * Sets the media
     *
     * @param ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference> $media
     * @return void
     */
    public function setMedia(ObjectStorage $media): void
    {
        $this->media = $media;
    }

    public function getMediaPublicUrl(): string
    {
        $file = $this->getFirstMedia();
        return $file && $file->getOriginalResource() ? (string)$file->getOriginalResource()->getPublicUrl() : '';
    }

    private function getFirstMedia(): ?FileReference
    {
        $this->media->rewind();
        if (!$this->media->valid()) {
            return null;
        }
        return $this->media->current();
    }

    public function getLegitimationUser(): ?User
    {
        return $this->legitimationUser;
    }

    public function setLegitimationUser(User $legitimationUser): void
    {
        $this->legitimationUser = $legitimationUser;
    }

    public function getLegitimationDate(): int
    {
        return $this->legitimationDate;
    }

    public function setLegitimationDate(int $legitimationDate): void
    {
        $this->legitimationDate = $legitimationDate;
    }

    /**
     * Returns the links
     *
     * @return ObjectStorage<\SkillDisplay\Skills\Domain\Model\Link>
     */
    public function getLinks()
    {
        return $this->links;
    }

    public function getSyllabusLayoutFile(): int
    {
        return $this->syllabusLayoutFile;
    }

    public function setSyllabusLayoutFile(int $syllabusLayoutFile): void
    {
        $this->syllabusLayoutFile = $syllabusLayoutFile;
    }

    /**
     * Sets the links
     *
     * @param ObjectStorage<\SkillDisplay\Skills\Domain\Model\Link> $links
     * @return void
     */
    public function setLinks(ObjectStorage $links): void
    {
        $this->links = $links;
    }

    /**
     * @return Skill[]
     */
    public function getRecommendedSkills(): array
    {
        $skills = [];
        $skillIds = array_map(function (Skill $skill) {return $skill->getUid();}, $this->skills->toArray());
        foreach ($this->skills as $skill) {
            if (!$skill->getCompletedInformation()->isCompleted() && $skill->getCanBeTakenByUser([], $skillIds)) {
                $skills[] = $skill;
            }
        }
        return $skills;
    }

    public function getProgress(): array
    {
        $verificationService = GeneralUtility::makeInstance(ObjectManager::class)->get(VerificationService::class);
        $stats = $this->getCompletedInformation()->getStatistics();
        $skills = $this->skills->toArray();
        return [
            'self' => !empty($stats['pending']['tier3']) ? 1 : (!empty($stats['granted']['tier3']) ? 0 : 2),
            'education' => !empty($stats['pending']['tier2']) ? 1 : (!empty($stats['granted']['tier2']) ? 0 : 2),
            'business' => !empty($stats['pending']['tier4']) ? 1 : (!empty($stats['granted']['tier4']) ? 0 : 2),
            'certificate' => !empty($stats['pending']['tier1']) ? 1 : (!empty($stats['granted']['tier1']) ? 0 : 2),
            'selfDisabled' => empty($skills),
            'educationDisabled' => $GLOBALS['reducedProgress'] ?? (!$this->user || empty($verificationService->getVerifiersForSkills($skills, $this->user, 2))),
            'businessDisabled' => $GLOBALS['reducedProgress'] ?? (!$this->user || empty($verificationService->getVerifiersForSkills($skills, $this->user, 4))),
            'certificateDisabled' => $GLOBALS['reducedProgress'] ?? (!$this->user || empty($verificationService->getVerifiersForSkills($skills, $this->user, 1))),
            'educationPendingId' => !empty($stats['pending']['tier2']) ? $stats['pending']['tier2'][0] : 0,
            'businessPendingId' => !empty($stats['pending']['tier4']) ? $stats['pending']['tier4'][0] : 0,
            'certificatePendingId' => !empty($stats['pending']['tier1']) ? $stats['pending']['tier1'][0] : 0,
        ];
    }

    public function getExportJson(): string
    {
        $data = [
            "tstamp" => $this->tstamp,
            "name" => $this->getName(),
            "description" => $this->getDescription(),
            "visibility" => $this->getVisibility(),
            "translations" => ExportService::getTranslations('tx_skills_domain_model_skillpath', $this->getUid(), self::TRANSLATE_FIELDS),
        ];

        $links = [];
        foreach($this->getLinks() as $link) {
            $links[] = $link->getUUId();
        }

        $brands = [];
        foreach($this->getBrands() as $brand) {
            $brands[] = $brand->getUUId();
        }

        $skills = [];
        foreach ($this->getSkills() as $skill) {
            $skills[] = $skill->getUUId();
        }

        $set = [
            'uuid' => $this->uuid,
            'type' => get_class($this),
            "uid" => $this->getUid(),
            'data' => $data,
        ];

        $set['links'] = $links;
        $set['brands'] = $brands;
        $set['skills'] = $skills;

        $file = $this->getFirstMedia();
        if ($file && $file->getOriginalResource()) {
            ExportService::encodeFileReference($file->getOriginalResource(), $set, 'media');
        }

        return json_encode($set);
    }

    public function addCategory(Category $category): void
    {
        $this->categories->attach($category);
    }

    public function removeCategory(Category $category): void
    {
        $this->categories->detach($category);
    }

    /**
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\Category>
     */
    public function getCategories()
    {
        return $this->categories;
    }

    public function setCategories($categories): void
    {
        $this->categories = $categories;
    }

    public function getFirstCategory(): ?Category
    {
        return $this->categories ? ($this->categories->toArray()[0] ?? null) : null;
    }

    public function getFirstCategoryTitle(): string
    {
        return $this->getFirstCategory() ? $this->getFirstCategory()->getTitle() : '';
    }


    public function getUUId(): string
    {
        return $this->uuid;
    }

    public function getCertificateLink(): string
    {
        return $this->certificateLink;
    }

    public function setCertificateLink(string $certificateLink): void
    {
        $this->certificateLink = $certificateLink;
    }

    public function getCertificateLayoutFile(): int
    {
        return $this->certificateLayoutFile;
    }

    public function setCertificateLayoutFile(int $certificateLayoutFile): void
    {
        $this->certificateLayoutFile = $certificateLayoutFile;
    }

    public function hasCertificate() : bool
    {
        return !empty($this->certificateLink);
    }

    public function getVisibility(): int
    {
        return $this->visibility;
    }

    public function setVisibility(int $visibility): void
    {
        $this->visibility = $visibility;
    }

    public function setRecommendedSkillSets(array $sets): void
    {
        $this->recommendedSkillSets = $sets;
    }

    public function getRecommendedSkillSets(): array
    {
        return $this->recommendedSkillSets;
    }

    public function getPopularityLog2(): float
    {
        return $this->popularityLog2;
    }

    public function setPopularityLog2(float $popularityLog2): void
    {
        $this->popularityLog2 = $popularityLog2;
    }
}
