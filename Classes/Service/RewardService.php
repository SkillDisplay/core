<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skills" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2018 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Service;

use SkillDisplay\Skills\Domain\Model\GrantedReward;
use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\GrantedRewardRepository;
use SkillDisplay\Skills\Domain\Repository\RewardRepository;
use SkillDisplay\Skills\Event\VerificationUpdatedEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class RewardService
{
    /**
     * Slot implementation for "certificationUpdated"
     *
     * @param VerificationUpdatedEvent $event
     * @throws Exception
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     */
    public function checkRewardsReachedForCertifications(VerificationUpdatedEvent $event): void
    {
        /** @var RewardRepository $rewardRepository */
        $rewardRepository = GeneralUtility::makeInstance(RewardRepository::class);
        $verifications = $event->getVerifications();
        foreach ($verifications as $cert) {
            if ((!$cert->getGrantDate() || !$cert->isValid()) && !$cert->getRevokeDate()) {
                continue;
            }
            $user = $cert->getUser();
            $skill = $cert->getSkill();
            $skillSet = $cert->getRequestGroupParent();
            $skillRewards = $rewardRepository->findByCertification($cert);
            $skillSetRewards = $rewardRepository->findForSkillSets($user, strval($cert->getLevelNumber()));
            foreach ($skillRewards as $reward) {
                $this->checkRewardReached($user, $reward, $skill);
            }

            foreach ($skillSetRewards as $reward) {
                $this->checkRewardReached($user, $reward, $skill, $skillSet);
            }
        }
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     */
    public function checkRewardReached(User $user, Reward $reward, Skill $skill = null, SkillPath $skillSet = null): void
    {
        $grantedRepo = GeneralUtility::makeInstance(GrantedRewardRepository::class);
        $existingReward = $grantedRepo->findByRewardAndUser($reward, $user);
        if ($existingReward->count()) {
            return;
        }
        $isReached = false;
        if ($reward->getSkillpath()) {
            if ($skillSet && $skillSet->getUid() === $reward->getSkillpath()->getUid()) {
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
            $grant = GeneralUtility::makeInstance(GrantedReward::class);
            $grant->setReward($reward);
            $grant->setUser($user);
            $grant->setCrdate($GLOBALS['EXEC_TIME']);
            $grant->setValidUntil($reward->getValidUntil());
            $grantedRepo->add($grant);
            GeneralUtility::makeInstance(PersistenceManager::class)->persistAll();
        }
    }
}
