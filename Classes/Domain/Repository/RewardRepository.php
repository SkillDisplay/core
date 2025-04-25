<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
**/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\GrantedReward;
use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for Rewards
 */
class RewardRepository extends BaseRepository
{
    public function findAllBackend(): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setIgnoreEnableFields(true);
        return $q->execute();
    }

    public function findForSkillSets(User $user, string $level): QueryResultInterface
    {
        $q = $this->createQuery();
        $constraints = $this->getAvailabilityConstraints($q, $user, false);
        $constraints[] = $q->logicalNot($q->equals('skillpath', 0));
        $constraints[] = $q->equals('level', $level);
        $q->matching($q->logicalAnd(...$constraints));
        return $q->execute();
    }

    /**
     * @param Certification $cert
     * @return QueryResultInterface
     */
    public function findByCertification(Certification $cert): QueryResultInterface
    {
        $q = $this->createQuery();
        $constraints = $this->getAvailabilityConstraints($q, $cert->getUser());
        $constraints[] = $q->equals('prerequisites.level', $cert->getLevelNumber());
        $brandConstraints = [
            $q->equals('prerequisites.brand', 0),
        ];
        if ($cert->getBrand()) {
            $brandConstraints[] = $q->equals('prerequisites.brand', $cert->getBrand()->getUid());
        }
        if (count($brandConstraints) > 1) {
            $constraints[] = $q->logicalOr(...$brandConstraints);
        } else {
            $constraints[] = $brandConstraints[0];
        }
        $q->matching($q->logicalAnd(...$constraints));
        return $q->execute();
    }

    /**
     * @param User $user
     * @param Skill|null $skill
     * @return Reward[]
     * @throws InvalidQueryException
     */
    public function findAvailableForUser(User $user, ?Skill $skill = null): array
    {
        $grantedRepo = GeneralUtility::makeInstance(GrantedRewardRepository::class);
        $certRepo = GeneralUtility::makeInstance(CertificationRepository::class);

        $grantedRewardIds = array_map(
            fn(GrantedReward $reward) => $reward->getReward()->getUid(),
            $grantedRepo->findByUser($user)->toArray()
        );

        $q = $this->createQuery();
        $constraints = $this->getAvailabilityConstraints($q, $user);
        if (!empty($grantedRewardIds)) {
            $constraints[] = $q->logicalNot($q->in('uid', $grantedRewardIds));
        }
        if ($skill) {
            $constraints[] = $q->equals('prerequisites.skill', $skill->getUid());
        }
        $q->matching($q->logicalAnd(...$constraints));
        $availableRewards = $q->execute();

        $rewardsToTakeByMissingSkills = [];
        /** @var Reward $reward */
        foreach ($availableRewards as $reward) {
            $missing = 0;
            foreach ($reward->getPrerequisites() as $prerequisite) {
                if (!$certRepo->findByPrerequisiteAndUser($prerequisite, $user)->count()) {
                    $missing++;
                }
            }
            if ($missing) {
                $rewardsToTakeByMissingSkills[$missing][] = $reward;
            }
        }
        ksort($rewardsToTakeByMissingSkills);
        $rewardsToTake = [];
        foreach ($rewardsToTakeByMissingSkills as $rewards) {
            foreach ($rewards as $reward) {
                $rewardsToTake[] = $reward;
            }
        }
        return $rewardsToTake;
    }

    private function getAvailabilityConstraints(QueryInterface $q, ?User $user = null, bool $withSkill = true): array
    {
        $orgaConstraint = [
            $q->equals('validForOrganisation', 0),
        ];
        if ($user && $user->getOrganisations()->count()) {
            $organisationIds = array_map(fn(Brand $b) => $b->getUid(), $user->getOrganisations()->toArray());
            $orgaConstraint[] = $q->in('validForOrganisation', $organisationIds);
        }
        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $constraints = [
            $q->logicalOr(
                $q->equals('availabilityStart', 0),
                $q->lessThanOrEqual('availabilityStart', $now)
            ),
            $q->logicalOr(
                $q->equals('availabilityEnd', 0),
                $q->greaterThan('availabilityEnd', $now),
            ),
        ];
        if (count($orgaConstraint) > 1) {
            $constraints[] = $q->logicalOr(...$orgaConstraint);
        } else {
            $constraints[] = $orgaConstraint[0];
        }
        if ($withSkill) {
            $constraints[] = $q->logicalNot($q->equals('prerequisites.skill', 0));
        }
        return $constraints;
    }

    /**
     * @return Reward[]
     */
    public function getAllForSkillPath(User $user, SkillPath $set): array
    {
        $q = $this->createQuery();
        $constraints = $this->getAvailabilityConstraints($q, $user, false);
        $constraints[] = $q->equals('skillpath', $set);
        $q->matching($q->logicalAnd(...$constraints));
        /** @var Reward[] $res */
        $res = $q->execute()->toArray();
        return $res;
    }

    /**
     * @return Reward[]
     */
    public function getAllForSkillSetWithoutConstraints(SkillPath $set): array
    {
        $q = $this->createQuery();
        $constraints[] = $q->equals('skillpath', $set);
        $q->matching($q->logicalAnd(...$constraints));
        /** @var Reward[] $res */
        $res = $q->execute()->toArray();
        return $res;
    }
}
