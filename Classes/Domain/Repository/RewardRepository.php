<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\GrantedReward;
use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for Rewards
 */
class RewardRepository extends BaseRepository
{

    public function findAllBackend() : QueryResultInterface
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setIgnoreEnableFields(true);
        return $q->execute();
    }

    public function findForSkillSets(User $user, string $level)
    {
        $q = $this->createQuery();
        $constraints = $this->getAvailabilityConstraints($q, $user, false);
        $constraints[] = $q->logicalOr($q->logicalNot($q->equals('skillpath', 0)));
        $constraints[] = $q->logicalOr($q->equals('level', $level));
        $q->matching($q->logicalAnd($constraints));
        return $q->execute();
    }

    public function findByCertification(Certification $cert): QueryResultInterface
    {
        $q = $this->createQuery();
        $constraints = $this->getAvailabilityConstraints($q, $cert->getUser());
        $constraints[] = $q->equals('prerequisites.level', $cert->getLevelNumber());
        $brand = [
            $q->equals('prerequisites.brand', 0)
        ];
        if ($cert->getBrand()) {
            $brand[] = $q->equals('prerequisites.brand', $cert->getBrand()->getUid());
        }
        $constraints[] = $q->logicalOr($brand);
        $q->matching(
            $q->logicalAnd($constraints)
        );
        return $q->execute();
    }

    public function findAvailableForUser(User $user, Skill $skill = null): array
    {
        $grantedRepo = $this->objectManager->get(GrantedRewardRepository::class);
        $certRepo = $this->objectManager->get(CertificationRepository::class);

        $grantedRewardIds = array_map(
            function (GrantedReward $reward) {
                return $reward->getReward()->getUid();
            },
            $grantedRepo->findByUser($user)->toArray()
        );

        $q = $this->createQuery();
        $constraints = $this->getAvailabilityConstraints($q, $user);
        if (!empty($grantedRewardIds)) {
            $constraints[] = $q->logicalNot($q->in('uid', $grantedRewardIds));
        }
        if ($skill) {
            $constraints[] = $q->equals('prerequisites.skill', $skill);
        }
        $q->matching($q->logicalAnd($constraints));
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

    private function getAvailabilityConstraints(QueryInterface $q, User $user = null, bool $withSkill = true): array
    {
        $orgaConstraint = [
            $q->equals('validForOrganisation', 0)
        ];
        if ($user && $user->getOrganisations()->count()) {
            $orgaConstraint[] = $q->in('validForOrganisation', $user->getOrganisations());
        }
        $now = time();
        $constraints = [
            $q->logicalOr([
                $q->equals('availabilityStart', 0),
                $q->lessThanOrEqual('availabilityStart', $now),
            ]),
            $q->logicalOr([
                $q->equals('availabilityEnd', 0),
                $q->greaterThan('availabilityEnd', $now),
            ]),
            $q->logicalOr($orgaConstraint)
        ];
        if ($withSkill) {
            $constraints[] = $q->logicalNot($q->equals('prerequisites.skill', 0));
        }
        return $constraints;
    }

    public function getAllForSkillPath(User $user, SkillPath $set): array
    {
        $q = $this->createQuery();
        $constraints = $this->getAvailabilityConstraints($q, $user, false);
        $constraints[] = $q->logicalOr($q->equals('skillpath', $set));
        $q->matching($q->logicalAnd($constraints));
        return $q->execute()->toArray();
    }

    public function getAllForSkillSetWithoutConstraints(SkillPath $set): array
    {
        $q = $this->createQuery();
        $constraints[] = $q->logicalOr($q->equals('skillpath', $set));
        $q->matching($q->logicalAnd($constraints));
        return $q->execute()->toArray();
    }
}
