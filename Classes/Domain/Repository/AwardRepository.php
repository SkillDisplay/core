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

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Award;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * The repository for awards
 */
class AwardRepository extends BaseRepository
{
    public function getBestThreeAwardsForUser(User $user): array
    {
        $q = $this->createQuery();
        $q->matching(
            $q->logicalAnd([
                $q->equals('user', $user),
                $q->equals('type', Award::TYPE_VERIFICATIONS)
            ])
        );
        $q->setOrderings(['rank' => QueryInterface::ORDER_DESCENDING]);
        $q->setLimit(3);
        /** @var Award[] $verified */
        $verified = $q->execute()->toArray();

        $q = $this->createQuery();
        $q->matching(
            $q->logicalAnd([
                $q->equals('user', $user),
                $q->equals('type', Award::TYPE_MEMBER)
            ])
        );
        $q->setOrderings(['rank' => QueryInterface::ORDER_DESCENDING]);
        /** @var Award $member */
        $member = $q->execute()->getFirst();

        $q = $this->createQuery();
        $q->matching(
            $q->logicalAnd([
                $q->equals('user', $user),
                $q->in('type', [Award::TYPE_MENTOR, Award::TYPE_COACH]),
            ])
        );
        $q->setOrderings(['rank' => QueryInterface::ORDER_DESCENDING]);
        /** @var Award $mentor */
        $mentor = $q->execute()->getFirst();

        $awards = [];
        if ($verified) {
            $awards[] = array_shift($verified);
        }
        if ($mentor != null && $member != null) {
            $awards[] = $member;
            $awards[] = $mentor;
        }
        elseif ($mentor == null && $member == null) {
            $awards = array_merge($awards, $verified);
        }
        else {
            if ($verified) {
                $awards[] = array_shift($verified);
            }
            $awards[] = $member ?: $mentor;
        }

        return $awards;
    }

    public function getAwardsByType(int $type): array
    {
        $q = $this->createQuery();
        $q->matching(
                $q->equals('type', $type)
        );
       return $q->execute()->toArray();
    }

    public function getAwardsByUserId(int $userId): array
    {
        $q = $this->createQuery();
        $q->matching(
            $q->equals('user', $userId)
        );
        return $q->execute()->toArray();
    }
}
