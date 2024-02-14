<?php

declare(strict_types=1);
/**
 * This file is part of the "Skills" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2018 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Service;

use SkillDisplay\Skills\Domain\Model\GrantedReward;
use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\GrantedRewardRepository;
use SkillDisplay\Skills\Domain\Repository\RewardRepository;
use SkillDisplay\Skills\Event\VerificationUpdatedEvent;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

readonly class RewardService
{
    public function __construct(
        protected RewardRepository $rewardRepository,
        protected GrantedRewardRepository $grantedRewardRepository,
    ) {}

    /**
     * Slot implementation for "certificationUpdated"
     *
     * @param VerificationUpdatedEvent $event
     * @throws AspectNotFoundException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     */
    public function checkRewardsReachedForCertifications(VerificationUpdatedEvent $event): void
    {
        $verifications = $event->getVerifications();
        foreach ($verifications as $cert) {
            if ((!$cert->getGrantDate() || !$cert->isValid()) && !$cert->getRevokeDate()) {
                continue;
            }
            $user = $cert->getUser();
            $skill = $cert->getSkill();
            $skillRewards = $this->rewardRepository->findByCertification($cert);
            $skillSetRewards = $this->rewardRepository->findForSkillSets($user, (string)($cert->getLevelNumber()));
            foreach ($skillRewards as $reward) {
                $this->checkRewardReached($user, $reward, $skill);
            }

            /** @var Reward $reward */
            foreach ($skillSetRewards as $reward) {
                $this->checkRewardReached($user, $reward, $skill, $cert->getRequestGroupParent());
            }
        }
    }

    /**
     * @param User $user
     * @param Reward $reward
     * @param Skill|null $skill
     * @param SkillPath|null $requestGroupSkillSet
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws AspectNotFoundException
     */
    public function checkRewardReached(User $user, Reward $reward, ?Skill $skill = null, ?SkillPath $requestGroupSkillSet = null): void
    {
        $existingReward = $this->grantedRewardRepository->findByRewardAndUser($reward, $user);
        if ($existingReward->count()) {
            return;
        }
        $isReached = false;
        if ($reward->getSkillpath()) {
            if ($requestGroupSkillSet && $requestGroupSkillSet->getUid() === $reward->getSkillpath()->getUid()) {
                $isReached = true;
            } else {
                $skillSetsOfSkill = $skill->getContainingPaths();
                foreach ($skillSetsOfSkill as $skillSetOfSkill) {
                    if ($skillSetOfSkill->getUid() === $reward->getSkillpath()->getUid()) {
                        $skillSetOfSkill->setUserForCompletedChecks($user);
                        $progressPercentage = $skillSetOfSkill->getProgressPercentage();
                        if ($progressPercentage['tier' . $reward->getLevel()] === 100.0) {
                            $isReached = true;
                            break;
                        }
                    }
                }
            }
        } else {
            $isReached = true;
            foreach ($reward->getPrerequisites() as $prerequisite) {
                // current skill is fulfilled by definition
                if ($skill && $prerequisite->getSkill()->getUid() === $skill->getUid()) {
                    continue;
                }
                $hasPrerequ = $user->hasRewardPrerequisite($prerequisite);
                if (!$hasPrerequ) {
                    $isReached = false;
                    break;
                }
            }
        }
        if ($isReached) {
            $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');

            $grant = new GrantedReward();
            $grant->setReward($reward);
            $grant->setUser($user);
            $grant->setCrdate($now);
            $grant->setValidUntil($reward->getValidUntil());
            $this->grantedRewardRepository->add($grant);

            GeneralUtility::makeInstance(PersistenceManager::class)->persistAll();
        }
    }
}
