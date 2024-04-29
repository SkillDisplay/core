<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *           Georg Ringer
 **/

namespace SkillDisplay\Skills\Domain\Model;

use DateTime;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Service\CertoBot;
use SkillDisplay\Skills\Service\Importer\ExportService;
use SkillDisplay\Skills\Service\VerificationService;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyObjectStorage;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Skill extends AbstractEntity
{
    public const VISIBILITY_PUBLIC = 0;
    public const VISIBILITY_ORGANISATION = 1;

    public const JsonViewConfiguration = [
        '_only' => [
            'uid',
            'title',
            'description',
            'goals',
            'links',
            'prerequisites',
            'progress',
            'brands',
            'domainTag',
            'tags',
            'dormant',
            'owner',
        ],
        '_descend' => [
            'dormant' => [],
            'links' => [
                '_descendAll' => [
                    '_only' => ['title', 'url', 'icon'],
                ],
            ],
            'prerequisites' => [
                '_descendAll' => [
                    '_only' => [
                        'uid',
                        'title',
                        'progress',
                        'dormant',
                    ],
                    '_descend' => [
                        'progress' => [],
                    ],
                ],
            ],
            'progress' => [],
            'brands' => [
                '_descendAll' => [
                    '_only' => [
                        'uid',
                        'name',
                        'description',
                        'logoPublicUrl',
                    ],
                ],
            ],
            'domainTag' => [
                '_only' => [
                    'uid',
                    'title',
                ],
            ],
            'tags' => [
                '_descendAll' => [
                    '_only' => [
                        'title',
                    ],
                ],
            ],
            'owner' => [
                '_only' => [
                    'uid',
                    'firstName',
                    'lastName',
                    'userAvatar',
                ],
            ],
        ],
    ];

    public const TRANSLATE_FIELDS = [
        'title',
        'description',
        'icon',
        'placeholder',
        'goals',
    ];

    public const LevelTierMap = [
        'undefined' => 0,
        'self' => 3,
        'education' => 2,
        'business' => 4,
        'certificate' => 1,
        'certification' => 1,
        'tier3' => 3,
        'tier2' => 2,
        'tier1' => 1,
        'tier4' => 4,
    ];

    protected SkillRepository $skillRepository;
    protected CertificationRepository $certificationRepository;
    protected SkillPathRepository $skillPathRepository;

    private ?User $user = null;

    /**
     * @Validate("NotEmpty")
     */
    protected string $title = '';
    protected string $description = '';
    protected string $goals = '';
    protected string $icon = '';

    /**
     * @Lazy
     * @Cascade("remove")
     */
    protected FileReference|LazyLoadingProxy|null $image = null;

    /**
     * @var ObjectStorage<Brand>
     * @Lazy
     */
    protected ObjectStorage|LazyObjectStorage $brands;

    /**
     * @var ObjectStorage<Tag>
     * @Lazy
     */
    protected ObjectStorage|LazyObjectStorage $tags;

    protected ?Tag $domainTag = null;

    /**
     * @var ObjectStorage<Link>
     * @Lazy
     */
    protected ObjectStorage|LazyObjectStorage $links;

    /**
     * @var ObjectStorage<Requirement>
     * @Lazy
     * @Cascade("remove")
     */
    protected ObjectStorage|LazyObjectStorage $requirements;

    protected bool $placeholder = false;
    protected ?DateTime $dormant = null;
    protected ?User $owner = null;
    protected int $tstamp = 0;
    protected string $uuid = '';
    protected int $imported = 0;
    private ?array $progressCache = null;
    protected int $visibility = 0;

    /**
     * non-persisted property
     */
    protected array $recommendedSkillSets = [];

    public function __construct()
    {
        $this->brands = new ObjectStorage();
        $this->tags = new ObjectStorage();
        $this->links = new ObjectStorage();
        $this->requirements = new ObjectStorage();
        $this->uuid = CertoBot::uuid();
    }

    public function getSingleProgressPercentage(): array
    {
        $stats = [
            'tier3' => 0,
            'tier2' => 0,
            'tier1' => 0,
            'tier4' => 0,
        ];
        if (!$this->user) {
            return $stats;
        }
        $cacheKey = 'percent_' . $this->user->getUid() . '_' . $this->getUid();
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('skill_progress');
        $cachedStats = $cache->get($cacheKey);
        if ($cachedStats) {
            return $cachedStats;
        }
        $certifications = $this->certificationRepository->findBySkillsAndUser([$this], $this->user);
        foreach ($certifications as $csor) {
            if ($csor->getGrantDate() && !$csor->getDenyDate() && !$csor->getRevokeDate()) {
                $stats[$csor->getLevel()] = 100;
            }
        }
        $cache->set($cacheKey, $stats, [$this->getCacheTag($this->user->getUid())]);
        return $stats;
    }

    public function setUserForCompletedChecks(User $user): void
    {
        if ($this->user === $user) {
            return;
        }
        $this->user = $user;
        /** @var Requirement $requirement */
        foreach ($this->getRequirements() as $requirement) {
            /** @var Set $set */
            foreach ($requirement->getSets() as $set) {
                /** @var SetSkill $setskill */
                foreach ($set->getSkills() as $setskill) {
                    if ($setskill->getSkill()) {
                        $setskill->getSkill()->setUserForCompletedChecks($user);
                    }
                }
            }
        }
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Returns the requirements
     *
     * @return ObjectStorage<Requirement>
     */
    public function getRequirements(): ObjectStorage
    {
        return $this->requirements;
    }

    public function getValidRequirements(): array
    {
        $requirements = [];
        foreach ($this->getRequirements() as $requirement) {
            $validSets = 0;
            /** @var Set $set */
            foreach ($requirement->getSets() as $set) {
                $validSetSkills = 0;
                /** @var SetSkill $setskill */
                foreach ($set->getSkills() as $setskill) {
                    if ($setskill->getSkill()) {
                        $validSetSkills++;
                    }
                }
                // set is valid
                if ($validSetSkills) {
                    $validSets++;
                }
            }
            // requirement is valid
            if ($validSets) {
                $requirements[] = $requirement;
            }
        }
        return $requirements;
    }

    /**
     * Sets the requirements
     *
     * @param ObjectStorage<Requirement> $requirements
     */
    public function setRequirements(ObjectStorage $requirements): void
    {
        $this->requirements = $requirements;
    }

    public function getCompletedInformation(): CertificationStatistics
    {
        $stats = new CertificationStatistics();
        if (!$this->user) {
            $stats->seal();
            return $stats;
        }
        $cacheKey = 'completed_' . $this->user->getUid() . '_' . $this->getUid();
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('skill_progress');
        $cachedStats = $cache->get($cacheKey);
        if ($cachedStats) {
            return $cachedStats;
        }
        $certifications = $this->certificationRepository->findBySkillsAndUser([$this], $this->user);
        foreach ($certifications as $cert) {
            $stats->addCertification($cert);
        }
        $stats->seal();
        $cache->set($cacheKey, $stats, [$this->getCacheTag($this->user->getUid())]);
        return $stats;
    }

    public function isSkillable(): bool
    {
        return !$this->dormant;
    }

    /**
     * Checks if the given user can currently take the skill (requirements must be fulfilled)
     *
     * @param int[] $assumedSkillIds Assume these skills are granted already
     * @param array $onlyRequiredSkillIds Only take a skill as required if it is in this list
     * @return bool
     */
    public function getCanBeTakenByUser(array $assumedSkillIds = [], array $onlyRequiredSkillIds = []): bool
    {
        if (!$this->user || $this->dormant) {
            return false;
        }
        $requirements = $this->getValidRequirements();
        if (empty($requirements)) {
            return true;
        }
        $requirementsCompleted = true;
        /** @var Requirement $requirement */
        foreach ($requirements as $requirement) {
            $requirementCompleted = false;
            /** @var Set $set */
            foreach ($requirement->getSets() as $set) {
                $setCompleted = true;
                /** @var SetSkill $setskill */
                foreach ($set->getSkills() as $setskill) {
                    $skill = $setskill->getSkill();
                    if (!$skill || $onlyRequiredSkillIds && !in_array($skill->getUid(), $onlyRequiredSkillIds)) {
                        continue;
                    }
                    if (!in_array($skill->getUid(), $assumedSkillIds)) {
                        $skill->setUserForCompletedChecks($this->user);
                        // if one setskill is not completed the whole set is incomplete (AND)
                        if (!$skill->getCompletedInformation()->isCompleted()) {
                            $setCompleted = false;
                            break;
                        }
                    }
                }
                // if one set is completed, the whole requirement is completed (OR)
                if ($setCompleted) {
                    $requirementCompleted = true;
                    break;
                }
            }
            // if one requirement is not completed, the skill can not be taken (AND)
            if (!$requirementCompleted) {
                $requirementsCompleted = false;
                break;
            }
        }
        return $requirementsCompleted;
    }

    /**
     * @param bool $recursive
     * @return Skill[]
     */
    public function getPrerequisites(bool $recursive = false): array
    {
        $skills = [];
        foreach ($this->requirements as $req) {
            /** @var Set $set */
            foreach ($req->getSets() as $set) {
                /** @var SetSkill $skillRef */
                foreach ($set->getSkills() as $skillRef) {
                    $skill = $skillRef->getSkill();
                    if ($skill) {
                        $skills[] = $skill;
                        if ($recursive) {
                            $skills = array_merge($skills, $skill->getPrerequisites(true));
                        }
                    }
                }
            }
        }
        return $skills;
    }

    public function getProgress(): array
    {
        if ($this->progressCache) {
            return $this->progressCache;
        }

        $verificationService = GeneralUtility::makeInstance(VerificationService::class);
        $completedInformation = $this->getCompletedInformation();
        $stats = $completedInformation->getStatistics();
        $brandIds = $completedInformation->getBrandIds();
        $this->progressCache = [
            'self' => !empty($stats['pending']['tier3']) ? 1 : (!empty($stats['granted']['tier3']) ? 0 : 2),
            'education' => !empty($stats['pending']['tier2']) ? 1 : (!empty($stats['granted']['tier2']) ? 0 : 2),
            'business' => !empty($stats['pending']['tier4']) ? 1 : (!empty($stats['granted']['tier4']) ? 0 : 2),
            'certificate' => !empty($stats['pending']['tier1']) ? 1 : (!empty($stats['granted']['tier1']) ? 0 : 2),
            'selfDisabled' => false,
            'educationDisabled' => $GLOBALS['reducedProgress'] ?? (!$this->user || empty($verificationService->getVerifiersForSkills([$this], $this->user, 2))),
            'businessDisabled' => $GLOBALS['reducedProgress'] ?? (!$this->user || empty($verificationService->getVerifiersForSkills([$this], $this->user, 4))),
            'certificateDisabled' => $GLOBALS['reducedProgress'] ?? (!$this->user || empty($verificationService->getVerifiersForSkills([$this], $this->user, 1))),
            'educationPendingId' => !empty($stats['pending']['tier2']) ? $stats['pending']['tier2'][0] : 0,
            'businessPendingId' => !empty($stats['pending']['tier4']) ? $stats['pending']['tier4'][0] : 0,
            'certificatePendingId' => !empty($stats['pending']['tier1']) ? $stats['pending']['tier1'][0] : 0,
            'educationBrandIds' => $brandIds['tier2'] ?? [],
            'businessBrandIds' => $brandIds['tier4'] ?? [],
            'certificateBrandIds' => $brandIds['tier1'] ?? [],
        ];
        return $this->progressCache;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
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
     * @return ObjectStorage<Brand>
     */
    public function getBrands(): ObjectStorage
    {
        return $this->brands;
    }

    public function setBrands(ObjectStorage $brands): void
    {
        $this->brands = $brands;
    }

    public function addTag(Tag $tag): void
    {
        $this->tags->attach($tag);
    }

    public function removeTag(Tag $tagToRemove): void
    {
        $this->tags->detach($tagToRemove);
    }

    /**
     * Returns the tags
     *
     * @return ObjectStorage<Tag>
     */
    public function getTags(): ObjectStorage
    {
        return $this->tags;
    }

    public function setTags(ObjectStorage $tags): void
    {
        $this->tags = $tags;
    }

    public function getDomainTag(): ?Tag
    {
        return $this->domainTag;
    }

    public function setDomainTag(?Tag $domainTag): void
    {
        $this->domainTag = $domainTag;
    }

    public function addRequirement(Requirement $requirement): void
    {
        $this->requirements->attach($requirement);
    }

    public function removeRequirement(Requirement $requirementToRemove): void
    {
        $this->requirements->detach($requirementToRemove);
    }

    public function getImage(): ?FileReference
    {
        if ($this->image instanceof LazyLoadingProxy) {
            $this->image = $this->image->_loadRealInstance();
        }
        return $this->image;
    }

    public function setImage(FileReference $image): void
    {
        $this->image = $image;
    }

    public function addLink(Link $link): void
    {
        $this->links->attach($link);
    }

    public function removeLink(Link $linkToRemove): void
    {
        $this->links->detach($linkToRemove);
    }

    /**
     * Returns the links
     *
     * @return ObjectStorage<Link>
     */
    public function getLinks(): ObjectStorage
    {
        return $this->links;
    }

    /**
     * Sets the links
     *
     * @param ObjectStorage<Link> $links
     */
    public function setLinks(ObjectStorage $links): void
    {
        $this->links = $links;
    }

    public function getPlaceholder(): bool
    {
        return $this->placeholder;
    }

    public function setPlaceholder(bool $placeholder): void
    {
        $this->placeholder = $placeholder;
    }

    public function isPlaceholder(): bool
    {
        return $this->placeholder;
    }

    /**
     * Find all skills requiring this skill
     *
     * @return Skill[]
     */
    public function getSuccessorSkills(): array
    {
        return $this->skillRepository->findParents($this);
    }

    /**
     * @return SkillPath[]
     * @throws InvalidQueryException
     */
    public function getContainingPaths(): array
    {
        return $this->skillPathRepository->findBySkill($this);
    }

    public function getGoals(): string
    {
        return $this->goals;
    }

    public function setGoals(string $goals): void
    {
        $this->goals = $goals;
    }

    public function getDormant(): ?DateTime
    {
        return $this->dormant;
    }

    public function setDormant(?DateTime $dormant): void
    {
        $this->dormant = $dormant;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(User $owner = null): void
    {
        $this->owner = $owner;
    }

    public function injectCertificationRepository(CertificationRepository $certificationRepository): void
    {
        $this->certificationRepository = $certificationRepository;
    }

    public function injectSkillPathRepository(SkillPathRepository $skillPathRepository): void
    {
        $this->skillPathRepository = $skillPathRepository;
    }

    public function injectSkillRepository(SkillRepository $skillRepository): void
    {
        $this->skillRepository = $skillRepository;
    }

    public function getExportJson(): string
    {
        $data = [
            'tstamp' => $this->tstamp,
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'icon' => $this->getIcon(),
            'placeholder' => (int)$this->getPlaceholder(),
            'goals' => $this->getGoals(),
            'dormant' => $this->getDormant() ? $this->getDormant()->getTimestamp() : 0,
        ];

        $links = [];
        foreach ($this->getLinks() as $link) {
            $links[] = $link->getUUId();
        }

        $brands = [];
        foreach ($this->getBrands() as $brand) {
            $brands[] = $brand->getUUId();
        }

        $tags = [];
        foreach ($this->getTags() as $tag) {
            $tags[] = $tag->getUUId();
        }

        $requirements = [];
        foreach ($this->getRequirements() as $requirement) {
            $sets = [];
            foreach ($requirement->getSets() as $set) {
                $setGroup = [];
                /** @var Set $set*/
                foreach ($set->getSkills() as $setSkill) {
                    $skill1 = $setSkill->getSkill();
                    if ($skill1) {
                        $setGroup[] = [
                            'skill_uuid' => $skill1->getUUId(),
                            'skill_title' => $skill1->getTitle(),
                        ];
                    }
                }
                if (!empty($setGroup)) {
                    $sets[] = $setGroup;
                }
            }
            if (!empty($sets)) {
                $requirements[] = $sets;
            }
        }

        $data['translations'] = ExportService::getTranslations('tx_skills_domain_model_skill', $this->getUid(), self::TRANSLATE_FIELDS);

        $skill = [
            'uuid' => $this->uuid,
            'type' => get_class($this),
            'uid' => $this->getUid(),
            'data' => $data,
        ];

        $skill['links'] = $links;
        $skill['brands'] = $brands;
        $skill['tags'] = $tags;
        $skill['requirements'] = $requirements;
        if ($this->getDomainTag()) {
            $skill['domain_tag'] = $this->getDomainTag()->getUUId();
        }

        $image = $this->getImage();
        if ($image && $image->getOriginalResource()) {
            ExportService::encodeFileReference($image->getOriginalResource(), $skill, 'image');
        }

        return json_encode($skill);
    }

    public function getUUId(): string
    {
        return $this->uuid;
    }

    public function getVisibility(): int
    {
        return $this->visibility;
    }

    public function setVisibility(int $visibility): void
    {
        if ($visibility === self::VISIBILITY_PUBLIC || $visibility === self::VISIBILITY_ORGANISATION) {
            $this->visibility = $visibility;
        }
    }

    public function setRecommendedSkillSets(array $sets): void
    {
        $this->recommendedSkillSets = $sets;
    }

    public function getRecommendedSkillSets(): array
    {
        return $this->recommendedSkillSets;
    }

    public function getCacheTag(int $userId): string
    {
        return 'skill_' . $this->getUid() . '_user_' . $userId;
    }
}
