<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2018 Markus Klein
 *
 ***/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for Rewards
 */
class GrantedRewardRepository extends BaseRepository
{
    public function findByCreatedDate(User $user, int $tstamp): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->matching($q->logicalAnd([
            $q->equals('user', $user),
            $q->greaterThan('crdate', $tstamp)
        ]));
        return $q->execute();
    }

    public function findByUser(User $user): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->setOrderings(['crdate' => QueryInterface::ORDER_DESCENDING]);
        $q->matching($q->equals('user', $user));
        return $q->execute();
    }

    public function findByRewardAndUser(Reward $reward, User $user): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->setOrderings(['crdate' => QueryInterface::ORDER_DESCENDING]);
        $q->matching(
            $q->logicalAnd([
                $q->equals('user', $user),
                $q->equals('reward', $reward),
            ])
        );
        return $q->execute();
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException
     */
    public function getSelectedRewardsByUser(User $user): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->matching(
            $q->logicalAnd([
                $q->equals('user', $user->getUid()),
                $q->equals('selectedByUser', true),
                $q->equals('reward.type', Reward::TYPE_BADGE),
                $q->logicalOr([
                    $q->equals('validUntil', 0),
                    $q->greaterThan('validUntil', $GLOBALS['EXEC_TIME'])
                ]),
            ])
        );
        return $q->execute();
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException
     */
    public function getAllValidRewardsForUser(User $user): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->matching(
            $q->logicalAnd([
                $q->equals('user', $user->getUid()),
                $q->equals('reward.type', Reward::TYPE_BADGE),
                $q->logicalOr([
                    $q->equals('validUntil', 0),
                    $q->greaterThan('validUntil', $GLOBALS['EXEC_TIME'])
                ]),
            ])
        );
        return $q->execute();
    }
}
