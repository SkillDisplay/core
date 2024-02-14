<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Model;

use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Service\TestSystemProviderService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyObjectStorage;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Certifier extends AbstractEntity
{
    public const JsonViewConfiguration = [
        '_exclude' => ['sharedApiSecret'],
        '_descend' => [
            'brand' => [],
            'recentRequests' => [
                '_descendAll' => Certification::JsonViewConfiguration,
            ],
            'stats' => [],
        ],
    ];

    protected bool $public = false;
    protected ?User $user = null;
    protected string $link = '';
    protected string $testSystem = '';
    protected ?Brand $brand = null;
    protected string $sharedApiSecret = '';

    /**
     * @var ObjectStorage<CertifierPermission>
     * @Cascade("remove")
     * @Lazy
     */
    protected ObjectStorage|LazyObjectStorage $permissions;

    public function __construct()
    {
        $this->permissions = new ObjectStorage();
    }

    public function toJsonData(bool $addVerifications = false, int $recentLimit = 5): array
    {
        $recentRequests = [];
        $stats = [
            'pending' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'total' => 0,
        ];
        if ($addVerifications) {
            $certRepo = GeneralUtility::makeInstance(CertificationRepository::class);
            $groups = $certRepo->findByCertifier($this);

            foreach ($groups as $group) {
                /** @var Certification $verification */
                $verification = $group['certs'][0];

                if ($verification->getDenyDate() || $verification->getRevokeDate()) {
                    $stats['rejected']++;
                } elseif ($verification->getGrantDate() && $verification->getRevokeDate() === null) {
                    $stats['accepted']++;
                } else {
                    $stats['pending']++;
                    $data = $verification->toJsonData();
                    $data['crdate'] = $verification->getCrdate();
                    $data['skillCount'] = count($group['certs']);
                    $recentRequests[] = $data;
                }
                $stats['total']++;
            }
            usort($recentRequests, function (array $a, array $b) {
                return $b['crdate'] - $a['crdate'];
            });
            $recentRequests = array_slice($recentRequests, 0, $recentLimit);
        }
        $logoUrl = $this->brand
            ? ($this->brand->getLogoScaled() ? (string)$this->brand->getLogoScaled()->getPublicUrl() : '')
            : '';
        if ($this->getUser() !== null) {
            return [
                'uid' => $this->uid,
                'firstName' => $this->user ? $this->user->getFirstName() : '',
                'lastName' => $this->user ? $this->user->getLastName() : '',
                'imageUrl' => (string)$this->user?->getAvatarScaled()->getPublicUrl(),
                'favourite' => false,
                'brand' => [
                    'name' => $this->brand ? $this->brand->getName() : '',
                    'logoPublicUrl' => $logoUrl,
                ],
                'recentRequests' => $recentRequests,
                'stats' => $stats,
            ];
        }
        $providerService = GeneralUtility::makeInstance(TestSystemProviderService::class);
        return [
            'uid' => $this->uid,
            'testSystemId' => $this->getTestSystem(),
            'testSystemLabel' => $providerService->getProviderById($this->getTestSystem())->getLabel(),
            'brand' => [
                'name' => $this->brand ? $this->brand->getName() : '',
                'logoPublicUrl' => $logoUrl,
            ],
            'recentRequests' => $recentRequests,
            'stats' => $stats,
        ];

    }

    public function getPendingCertifications(): array
    {
        $certRepo = GeneralUtility::makeInstance(CertificationRepository::class);
        return $certRepo->findPendingByCertifier($this);
    }

    public function getCompletedCertifications(): array
    {
        $certRepo = GeneralUtility::makeInstance(CertificationRepository::class);
        return $certRepo->findCompletedByCertifier($this);
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand): void
    {
        $this->brand = $brand;
    }

    public function addPermission(CertifierPermission $permission): void
    {
        $this->permissions->attach($permission);
    }

    public function removePermission(CertifierPermission $permissionToRemove): void
    {
        $this->permissions->detach($permissionToRemove);
    }

    /**
     * @return ObjectStorage<CertifierPermission>
     */
    public function getPermissions(): ObjectStorage
    {
        return $this->permissions;
    }

    /**
     * @param ObjectStorage<CertifierPermission> $permissions
     */
    public function setPermissions(ObjectStorage $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link): void
    {
        $this->link = $link;
    }

    public function getSharedApiSecret(): string
    {
        return $this->sharedApiSecret;
    }

    public function setSharedApiSecret(string $sharedApiSecret): void
    {
        $this->sharedApiSecret = $sharedApiSecret;
    }

    public function getTestSystem(): string
    {
        return $this->testSystem;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }
}
