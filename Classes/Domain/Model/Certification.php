<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Domain\Model;

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *           Georg Ringer
 **/

use DateTime;
use InvalidArgumentException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Repository;

class Certification extends AbstractEntity
{
    public const TYPE_GROUPED_BY_DATE = 0;
    public const TYPE_GROUPED_BY_BRAND = 1;

    public const JsonViewConfiguration = [
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
            ],
        ],
        'skillSet' => [
            '_only' => [
                'uid',
                'name',
                'mediaPublicUrl',
                'brand',
                'firstCategoryTitle',
            ],
            '_descend' => [
                'brand' => Brand::JsonViewMinimalConfiguration,
            ],
        ],
    ];

    protected int $crdate = 0;
    protected bool $tier1 = false;
    protected bool $tier2 = false;
    protected bool $tier3 = false;
    protected bool $tier4 = false;
    protected ?DateTime $grantDate = null;
    protected ?DateTime $denyDate = null;
    protected ?DateTime $expireDate = null;
    protected ?DateTime $revokeDate = null;
    protected string $revokeReason = '';
    protected string $comment = '';
    protected string $requestGroup = '';
    protected string $skillTitle = '';
    protected string $userLastname = '';
    protected string $userFirstname = '';
    protected string $userUsername = '';
    protected string $verifierName = '';
    protected string $brandName = '';
    protected string $groupName = '';
    protected ?Skill $skill = null;

    /**
     * Certified user
     *
     * @var User|null
     */
    protected ?User $user = null;

    /**
     * Certifying person
     *
     * @var Certifier|null
     */
    protected ?Certifier $certifier = null;
    protected ?Brand $brand = null;
    protected ?Campaign $campaign = null;
    protected int $points = 0;
    protected float $price = 0.0;
    protected bool $rewardable = true;

    public function getLevel(): string
    {
        return 'tier' . $this->getLevelNumber();
    }

    public function getLevelNumber(): int
    {
        if ($this->getTier1()) {
            return 1;
        }
        if ($this->getTier2()) {
            return 2;
        }
        if ($this->getTier3()) {
            return 3;
        }
        if ($this->getTier4()) {
            return 4;
        }
        throw new InvalidArgumentException('A verification must have a level. uid:' . $this->uid);
    }

    public function isValid(): bool
    {
        return $this->grantDate && !$this->revokeDate && (!$this->expireDate || $this->expireDate->getTimestamp() > time());
    }

    public function isPending(): bool
    {
        return $this->grantDate === null && $this->denyDate === null;
    }

    public function getRequestGroupParent(): SkillPath|SkillGroup|null
    {
        if (!$this->requestGroup) {
            return null;
        }

        $parts = explode('-', $this->requestGroup);
        if (isset($GLOBALS['EXTCONF']['skills']['SkillGroups'][$parts[0]])) {
            /** @var Repository $repo */
            $repo = GeneralUtility::makeInstance($GLOBALS['EXTCONF']['skills']['SkillGroups'][$parts[0]]);
            /** @var SkillPath|SkillGroup $result */
            $result = $repo->findByUid((int)$parts[1]);
            return $result;
        }
        return null;
    }

    public function copy(): Certification
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
            'userAvatar' => '',
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
            'brandId' => $this->brand?->getUid(),
            'comment' => '',
            'reason' => '',
            'verifier' => null,
            'skillSet' => $skillsetData,
            'skills' => [],
            'skillId' => $this->skill?->getUid(),
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

    public function setRequestGroup(string $requestGroup): void
    {
        $this->requestGroup = $requestGroup;
    }

    public function getTier1(): bool
    {
        return $this->tier1;
    }

    public function isTier1(): bool
    {
        return $this->tier1;
    }

    public function setTier1(bool $tier1): void
    {
        $this->tier1 = $tier1;
    }

    public function getTier2(): bool
    {
        return $this->tier2;
    }

    public function isTier2(): bool
    {
        return $this->tier2;
    }

    public function setTier2(bool $tier2): void
    {
        $this->tier2 = $tier2;
    }

    public function getTier3(): bool
    {
        return $this->tier3;
    }

    public function isTier3(): bool
    {
        return $this->tier3;
    }

    public function setTier3(bool $tier3): void
    {
        $this->tier3 = $tier3;
    }

    public function getTier4(): bool
    {
        return $this->tier4;
    }

    public function isTier4(): bool
    {
        return $this->tier4;
    }

    public function setTier4(bool $tier4): void
    {
        $this->tier4 = $tier4;
    }

    public function getSkill(): ?Skill
    {
        return $this->skill;
    }

    public function setSkill(Skill $skill): void
    {
        $this->skill = $skill;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getGrantDate(): ?DateTime
    {
        return $this->grantDate;
    }

    public function setGrantDate(?DateTime $grantDate): void
    {
        $this->grantDate = $grantDate;
    }

    public function getDenyDate(): ?DateTime
    {
        return $this->denyDate;
    }

    public function setDenyDate(?DateTime $denyDate): void
    {
        $this->denyDate = $denyDate;
    }

    public function getExpireDate(): ?DateTime
    {
        return $this->expireDate;
    }

    public function isExpired(): bool
    {
        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        return $this->expireDate && $this->expireDate->getTimestamp() > $now;
    }

    public function setExpireDate(?DateTime $expireDate): void
    {
        $this->expireDate = $expireDate;
    }

    public function getRevokeDate(): ?DateTime
    {
        return $this->revokeDate;
    }

    public function setRevokeDate(?DateTime $revokeDate): void
    {
        $this->revokeDate = $revokeDate;
    }

    public function getCertifier(): ?Certifier
    {
        return $this->certifier;
    }

    public function setCertifier(?Certifier $certifier): void
    {
        $this->certifier = $certifier;
    }

    public function getRevokeReason(): string
    {
        return $this->revokeReason;
    }

    public function setRevokeReason(string $revokeReason): void
    {
        $this->revokeReason = $revokeReason;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function getCrdate(): int
    {
        return $this->crdate;
    }

    public function setCrdate(int $crdate): void
    {
        $this->crdate = $crdate;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    public function getCampaign(): ?Campaign
    {
        return $this->campaign;
    }

    public function setCampaign(?Campaign $campaign): void
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
