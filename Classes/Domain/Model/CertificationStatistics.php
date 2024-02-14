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

use RuntimeException;

class CertificationStatistics
{
    /**
     * @var Certification[][][]
     */
    private array $verifications = [
        'revoked' => [],
        'expired' => [],
        'granted' => [],
        'declined' => [],
        'pending' => [],
    ];

    /**
     * @var int[][][]
     */
    private array $statistics = [];

    private array $brandIds = [];

    public function addCertification(Certification $certification): void
    {
        if (!empty($this->statistics)) {
            throw new RuntimeException('Invalid access, must NOT be seal()d yet.');
        }
        if ($certification->getRevokeDate()) {
            $this->verifications['revoked'][$certification->getLevel()][] = $certification;
        } elseif ($certification->isExpired()) {
            $this->verifications['expired'][$certification->getLevel()][] = $certification;
        } elseif ($certification->getGrantDate()) {
            $this->verifications['granted'][$certification->getLevel()][] = $certification;
            $brand = $certification->getBrand();
            if (!empty($brand)) {
                $this->brandIds[$certification->getLevel()][] = $brand->getUid();
                $this->brandIds[$certification->getLevel()] = array_values(array_unique($this->brandIds[$certification->getLevel()]));
            }
        } elseif ($certification->getDenyDate()) {
            $this->verifications['declined'][$certification->getLevel()][] = $certification;
        } else {
            $this->verifications['pending'][$certification->getLevel()][] = $certification;
        }
    }

    public function removeVerificationsNotMatchingNumber(string $type, int $count): void
    {
        foreach ($this->verifications[$type] as $level => $grants) {
            if (count($grants) !== $count) {
                // remove all grants if not all where completed
                unset($this->verifications[$type][$level]);
            }
        }
    }

    public function removeNonGroupRequests(string $type, int $groupId): void
    {
        foreach ($this->verifications[$type] as $level => $pending) {
            $foundPending = false;
            /** @var Certification $cert */
            foreach ($pending as $cert) {
                $group = $cert->getRequestGroupParent();
                if ($group instanceof SkillPath && $group->getUid() === $groupId) {
                    $foundPending = true;
                    break;
                }
            }
            // if no pending group requests were found, remove all
            if (!$foundPending) {
                unset($this->verifications[$type][$level]);
            }
        }
    }

    public function seal(): void
    {
        foreach ($this->verifications as $type => $data) {
            foreach ($data as $level => $certs) {
                foreach ($certs as $index => $cert) {
                    $this->statistics[$type][$level][$index] = $cert->getUid();
                }
            }
        }
    }

    /**
     * @return int[][][]
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function getBrandIds(): array
    {
        return $this->brandIds;
    }

    public function isCompleted(): bool
    {
        return !empty($this->statistics['granted']);
    }

    public function __sleep(): array
    {
        return ['statistics', 'brandIds'];
    }
}
