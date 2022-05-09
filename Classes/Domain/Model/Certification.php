<?php declare(strict_types=1);
namespace SkillDisplay\Skills\Domain\Model;

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *           Georg Ringer
 *
 ***/

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class Certification extends AbstractEntity
{

    const TYPE_GROUPED_BY_DATE = 0;
    const TYPE_GROUPED_BY_BRAND = 1;

    const JsonViewConfiguration = [
        'skills' => [
            '_descendAll' => [
                '_only' => [
                    'uid',
                    'title',
                    'progress',
                ],
                '_descend' => [
                    'progress' => [],
                ],
            ]
        ],
        'skillSet' => [
            '_only' => [
                'uid',
                'name',
                'mediaPublicUrl',
                'brand',
                'firstCategoryTitle'
            ],
            '_descend' => [
                'brand' => Brand::JsonViewMinimalConfiguration
            ]
        ]
    ];

    /** @var int */
    protected $crdate = 0;

    /** @var bool */
    protected $tier1 = false;

    /** @var bool */
    protected $tier2 = false;

    /** @var bool */
    protected $tier3 = false;

    /** @var bool */
    protected $tier4 = false;

    /** @var \DateTime|null */
    protected $grantDate = null;

    /** @var \DateTime|null */
    protected $denyDate = null;

    /** @var \DateTime|null */
    protected $expireDate = null;

    /** @var \DateTime|null */
    protected $revokeDate = null;

    /** @var string */
    protected $revokeReason = '';

    /** @var string */
    protected $comment = '';

    /** @var string */
    protected $requestGroup = '';

    /** @var string */
    protected $skillTitle = '';

    /** @var string */
    protected $userLastname = '';

    /** @var string */
    protected $userFirstname = '';

    /** @var string */
    protected $userUsername = '';

    /** @var string */
    protected $verifierName = '';

    /** @var string */
    protected $brandName = '';

    /** @var string */
    protected $groupName = '';

    /** @var \SkillDisplay\Skills\Domain\Model\Skill */
    protected $skill = null;

    /**
     * Certified user
     *
     * @var \SkillDisplay\Skills\Domain\Model\User
     */
    protected $user = null;

    /**
     * Certifying person
     *
     * @var \SkillDisplay\Skills\Domain\Model\Certifier|null
     */
    protected $certifier = null;

    /** @var \SkillDisplay\Skills\Domain\Model\Brand|null */
    protected $brand = null;

    /**
     * @var \SkillDisplay\Skills\Domain\Model\Campaign|null
     */
    protected $campaign = null;

    protected int $points = 0;
    protected float $price = 0.0;

    protected bool $rewardable = true;

    public function getLevel(): string
    {
        return 'tier' . $this->getLevelNumber();
    }

    public function getLevelNumber() : int
    {
        if ($this->getTier1()) {
            return 1;
        } elseif ($this->getTier2()) {
            return 2;
        } elseif ($this->getTier3()) {
            return 3;
        } elseif ($this->getTier4()) {
            return 4;
        }
        throw new \InvalidArgumentException('A verification must have a level. uid:' . $this->uid);
    }

    public function setLevel(string $level)
    {
        $this->{$level} = true;
    }

    public function isValid() : bool
    {
        return $this->grantDate && !$this->revokeDate && (!$this->expireDate || $this->expireDate->getTimestamp() > time());
    }

    public function isPending() : bool
    {
        return $this->grantDate === null && $this->denyDate === null;
    }

    /**
     * @return SkillPath|SkillGroup|null
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function getRequestGroupParent()
    {
        if (!$this->requestGroup) {
            return null;
        }

        $parts = explode('-', $this->requestGroup);
        if (isset($GLOBALS['EXTCONF']['skills']['SkillGroups'][$parts[0]])) {
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            return $objectManager->get($GLOBALS['EXTCONF']['skills']['SkillGroups'][$parts[0]])->findByUid((int)$parts[1]);
        }
        return null;
    }

    public function copy() : Certification
    {
        $newCert = new self();
        $newCert->brand = $this->brand;
        $newCert->campaign = $this->campaign;
        $newCert->certifier = $this->certifier;
        $newCert->comment = $this->comment;
        $newCert->crdate = $this->crdate;
        $newCert->denyDate = $this->denyDate;
        $newCert->expireDate = $this->expireDate;
        $newCert->grantDate = $this->grantDate;
        $newCert->requestGroup = $this->requestGroup;
        $newCert->revokeDate = $this->revokeDate;
        $newCert->revokeReason = $this->revokeReason;
        $newCert->skill = $this->skill;
        $newCert->tier2 = $this->tier2;
        $newCert->tier3 = $this->tier3;
        $newCert->tier4 = $this->tier4;
        $newCert->user = $this->user;
        return $newCert;
    }

    public function toJsonData(bool $includePricing = false, bool $includeOnlyPublicInformation = false): array
    {
        $parent = $this->getRequestGroupParent();
        if ($parent instanceof SkillPath) {
            $skillsetData = $parent;
            $skillSetId = $parent->getUid();
            $title = $parent->getName();
        } elseif ($this->groupName !== '') {
            $skillsetData = [
                'brand' => [
                    'logoPublicUrl' => '',
                    'memberCount' => 0,
                ],
                'mediaPublicUrl' => '',
                'name' => $this->groupName,
            ];
            $skillSetId = null;
            $title = $this->groupName;
        } else {
            $skillsetData = null;
            $skillSetId = null;
            $title = $this->skill ? $this->skill->getTitle() : $this->skillTitle;
        }

        $user = $this->user ? $this->user->toMinimalJsonData() : [
            'uid' => 0,
            'firstName' => $this->getUserFirstname(),
            'lastName' => $this->getUserLastname(),
            'email' => '',
            'userAvatar' => ''
        ];

        $jsonData = [
            'uid' => $this->uid,
            'title' => $title,
            'crdate' => $this->crdate,
            'skillCount' => 1,
            'grantDate' => $this->grantDate,
            'denyDate' => $this->denyDate,
            'revokeDate' => $this->revokeDate,
            'type' => $this->getLevelNumber(),
            'user' => $user,
            'brandId' => $this->brand ? $this->brand->getUid() : null,
            'comment' => '',
            'reason' => '',
            'verifier' => null,
            'skillSet' => $skillsetData,
            'skills' => [],
            'skillId' => $this->skill ? $this->skill->getUid() : null,
            'skillSetId' => $skillSetId,
            'requestGroup' => $this->requestGroup,
            'canBeAccepted' => false,
        ];
        if (!$includeOnlyPublicInformation) {
            if ($this->certifier) {
                $verifierData = $this->certifier->toJsonData();
            } elseif ($this->verifierName !== '') {
                $nameSplit = explode(' ', $this->verifierName);
                $verifierData = [
                    'firstName' => $nameSplit[0],
                    'lastName' => $nameSplit[1],
                    'favourite' => false,
                    'imageUrl' => '',
                    'recentRequests' => [],
                    'brand' => [
                        'name' => $this->brandName,
                        'logoPublicUrl' => '',
                    ],
                ];
            } else {
                $verifierData = null;
            }

            $jsonData['verifier'] = $verifierData;
            $jsonData['reason'] = $this->revokeReason;
            $jsonData['comment'] = $this->comment;

            if ($includePricing) {
                $jsonData['price'] = $this->price;
                $jsonData['credits'] = $this->points;
            }
        }

        return $jsonData;
    }

    public function getRequestGroup(): string
    {
        return $this->requestGroup;
    }

    public function setRequestGroup(string $requestGroup)
    {
        $this->requestGroup = $requestGroup;
    }

    public function getTier1() : bool
    {
        return $this->tier1;
    }

    public function isTier1() : bool
    {
        return $this->tier1;
    }

    public function setTier1(bool $tier1)
    {
        $this->tier1 = $tier1;
    }

    public function getTier2() : bool
    {
        return $this->tier2;
    }

    public function isTier2() : bool
    {
        return $this->tier2;
    }

    public function setTier2(bool $tier2)
    {
        $this->tier2 = $tier2;
    }

    public function getTier3() : bool
    {
        return $this->tier3;
    }

    public function isTier3() : bool
    {
        return $this->tier3;
    }

    public function setTier3(bool $tier3)
    {
        $this->tier3 = $tier3;
    }

    public function getTier4() : bool
    {
        return $this->tier4;
    }

    public function isTier4() : bool
    {
        return $this->tier4;
    }

    public function setTier4(bool $tier4)
    {
        $this->tier4 = $tier4;
    }

    public function getSkill() : ?Skill
    {
        return $this->skill;
    }

    public function setSkill(Skill $skill)
    {
        $this->skill = $skill;
    }

    public function getUser() : ?User
    {
        return $this->user;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function getGrantDate() : ?\DateTime
    {
        return $this->grantDate;
    }

    public function setGrantDate(?\DateTime $grantDate)
    {
        $this->grantDate = $grantDate;
    }

    public function getDenyDate() : ?\DateTime
    {
        return $this->denyDate;
    }

    public function setDenyDate(?\DateTime $denyDate)
    {
        $this->denyDate = $denyDate;
    }

    public function getExpireDate(): ?\DateTime
    {
        return $this->expireDate;
    }

    public function isExpired(): bool
    {
        return $this->expireDate && $this->expireDate->getTimestamp() > $GLOBALS['EXEC_TIME'];
    }

    public function setExpireDate(?\DateTime $expireDate)
    {
        $this->expireDate = $expireDate;
    }

    public function getRevokeDate() : ?\DateTime
    {
        return $this->revokeDate;
    }

    public function setRevokeDate(?\DateTime $revokeDate)
    {
        $this->revokeDate = $revokeDate;
    }

    public function getCertifier()
    {
        return $this->certifier;
    }

    public function setCertifier(?Certifier $certifier)
    {
        $this->certifier = $certifier;
    }

    public function getRevokeReason() : string
    {
        return $this->revokeReason;
    }

    public function setRevokeReason(string $revokeReason)
    {
        $this->revokeReason = $revokeReason;
    }

    public function getBrand() : ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand)
    {
        $this->brand = $brand;
    }

    public function getCrdate() : int
    {
        return $this->crdate;
    }

    public function setCrdate(int $crdate)
    {
        $this->crdate = $crdate;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment)
    {
        $this->comment = $comment;
    }

    public function getCampaign()
    {
        return $this->campaign;
    }

    public function setCampaign(?Campaign $campaign)
    {
        $this->campaign = $campaign;
    }

    public function getSkillTitle(): string
    {
        return $this->skillTitle;
    }

    public function setSkillTitle(string $skillTitle): void
    {
        $this->skillTitle = $skillTitle;
    }

    public function getUserLastname(): string
    {
        return $this->userLastname;
    }

    public function setUserLastname(string $userLastname): void
    {
        $this->userLastname = $userLastname;
    }

    public function getUserFirstname(): string
    {
        return $this->userFirstname;
    }

    public function setUserFirstname(string $userFirstname): void
    {
        $this->userFirstname = $userFirstname;
    }

    public function getUserUsername(): string
    {
        return $this->userUsername;
    }

    public function setUserUsername(string $userUsername): void
    {
        $this->userUsername = $userUsername;
    }

    public function getVerifierName(): string
    {
        return $this->verifierName;
    }

    public function setVerifierName(string $verifierName): void
    {
        $this->verifierName = $verifierName;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function setBrandName(string $brandName): void
    {
        $this->brandName = $brandName;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function setGroupName(string $groupName): void
    {
        $this->groupName = $groupName;
    }

    public function isRewardable(): bool
    {
        return $this->rewardable;
    }

    public function setRewardable(bool $rewardable): void
    {
        $this->rewardable = $rewardable;
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
