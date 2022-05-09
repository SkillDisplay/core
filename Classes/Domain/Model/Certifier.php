<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Domain\Model;

use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Service\TestSystemProviderService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Certifier extends AbstractEntity
{
    const JsonViewConfiguration = [
        '_exclude' => ['sharedApiSecret'],
        '_descend' => [
            'brand' => [],
            'recentRequests' => [
                '_descendAll' => Certification::JsonViewConfiguration
            ],
            'stats' => [],
        ],
    ];

    /** @var string */
    protected $link = '';

    /** @var \SkillDisplay\Skills\Domain\Model\User */
    protected $user = null;

    /** @var string */
    protected string $testSystem = '';

    /** @var \SkillDisplay\Skills\Domain\Model\Brand */
    protected $brand = null;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\CertifierPermission>
     * @TYPO3\CMS\Extbase\Annotation\ORM\Cascade("remove")
     */
    protected $permissions = null;

    /** @var string */
    protected $sharedApiSecret = '';

    public function __construct()
    {
        $this->permissions = new ObjectStorage();
    }

    public function toJsonData(bool $addVerifications = false, int $recentLimit = 5) : array
    {
        $recentRequests = [];
        $stats = [
            'pending' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'total' => 0,
        ];
        if ($addVerifications) {
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            $certRepo = $objectManager->get(CertificationRepository::class);
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
            usort($recentRequests, function(array $a, array $b) {
                return $b['crdate'] - $a['crdate'];
            });
            $recentRequests = array_slice($recentRequests, 0, $recentLimit);
        }
        if ($this->getUser() !== null) {
            return [
                'uid' => $this->uid,
                'firstName' => $this->user ? $this->user->getFirstName() : '',
                'lastName' => $this->user ? $this->user->getLastName() : '',
                'imageUrl' => $this->user ? (string)$this->user->getAvatarScaled()->getPublicUrl() : '',
                'favourite' => false,
                'brand' => [
                    'name' => $this->brand ? $this->brand->getName() : '',
                    'logoPublicUrl' => $this->brand ? ($this->brand->getLogoScaled() ? (string)$this->brand->getLogoScaled()->getPublicUrl() : '') : '',
                ],
                'recentRequests' => $recentRequests,
                'stats' => $stats,
            ];
        } else {
            $providerService = GeneralUtility::makeInstance(TestSystemProviderService::class);
            return [
                'uid' => $this->uid,
                'testSystemId' => $this->getTestSystem(),
                'testSystemLabel' => $providerService->getProviderById($this->getTestSystem())->getLabel(),
                'brand' => [
                    'name' => $this->brand ? $this->brand->getName() : '',
                    'logoPublicUrl' => $this->brand ? ($this->brand->getLogoScaled() ? (string)$this->brand->getLogoScaled()->getPublicUrl() : '') : '',
                ],
                'recentRequests' => $recentRequests,
                'stats' => $stats,
            ];
        }

    }

    public function getPendingCertifications() : array
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $certRepo = $objectManager->get(CertificationRepository::class);
        return $certRepo->findPendingByCertifier($this);
    }

    public function getCompletedCertifications() : array
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $certRepo = $objectManager->get(CertificationRepository::class);
        return $certRepo->findCompletedByCertifier($this);
    }

    public function getUser() : ?User
    {
        return $this->user;
    }

    public function setUser(?User $user)
    {
        $this->user = $user;
    }

    public function getBrand() : ?Brand
    {
        return $this->brand;
    }

    public function setBrand(?Brand $brand)
    {
        $this->brand = $brand;
    }

    public function addPermission(CertifierPermission $permission)
    {
        $this->permissions->attach($permission);
    }

    public function removePermission(CertifierPermission $permissionToRemove)
    {
        $this->permissions->detach($permissionToRemove);
    }

    /**
     * @return ObjectStorage<CertifierPermission>
     */
    public function getPermissions() : ObjectStorage
    {
        return $this->permissions;
    }

    /**
     * @param ObjectStorage<CertifierPermission> $permissions
     */
    public function setPermissions(ObjectStorage $permissions)
    {
        $this->permissions = $permissions;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function setLink(string $link)
    {
        $this->link = $link;
    }

    /**
     * @return string
     */
    public function getSharedApiSecret(): string
    {
        return $this->sharedApiSecret;
    }

    /**
     * @param string $sharedApiSecret
     */
    public function setSharedApiSecret(string $sharedApiSecret): void
    {
        $this->sharedApiSecret = $sharedApiSecret;
    }

    /**
     * @return string
     */
    public function getTestSystem(): string
    {
        return $this->testSystem;
    }
}
