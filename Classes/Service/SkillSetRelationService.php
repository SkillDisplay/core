<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Service;

use Doctrine\DBAL\Driver\Exception;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\RecommendedSkillSetRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class SkillSetRelationService
{
    public const REGISTRY_SKILL_SETS = 'recommendationSetsToUpdate';
    public const REGISTRY_USERS = 'recommendationUsersToUpdate';

    public function __construct(
        protected readonly SkillPathRepository $skillSetRepository,
        protected readonly CertificationRepository $certificationRepository,
        protected readonly RecommendedSkillSetRepository $recommendedSkillSetRepository
    ) {}

    public function calculatePopularityForSets(): void
    {
        /** @var SkillPath $skillSet */
        foreach ($this->skillSetRepository->findAll() as $skillSet) {
            $verifications = $this->certificationRepository->findByGrantDateAndBrandsAndSkillSets(null, null, [], [$skillSet]);
            // ln(2) = 0.69314718055994530941723
            $log2 = log(max($verifications->count(), 2)) / 0.69314718055994530941723;
            $skillSet->setPopularityLog2($log2);
            $this->recommendedSkillSetRepository->updateSkillSetPopularity($skillSet);
            $this->recommendedSkillSetRepository->recalculateScore($skillSet);
        }
    }

    /**
     * @throws InvalidQueryException
     */
    public function updateScoreWithSet(User $user, SkillPath $skillSet): void
    {
        $this->recommendedSkillSetRepository->deleteRecommendations($user->getUid(), $skillSet->getUid(), 0);
        $this->recommendedSkillSetRepository->deleteRecommendations($user->getUid(), 0, $skillSet->getUid());

        $setsWithOverlap = $this->skillSetRepository->findOverlappingSets($skillSet, UserOrganisationsService::getOrganisationsOrEmpty($user));
        if ($setsWithOverlap->count()) {
            $this->calculateScoreWithRelatedSets($skillSet, $user, $setsWithOverlap);
            /** @var SkillPath $sourceSet */
            foreach ($setsWithOverlap as $sourceSet) {
                $this->calculateScoreWithRelatedSets($sourceSet, $user, [$skillSet]);
            }
        }
    }

    /**
     * @throws InvalidQueryException
     */
    public function calculateScoresBySourceSet(User $user, SkillPath $skillSet): void
    {
        $this->recommendedSkillSetRepository->deleteRecommendations($user->getUid(), $skillSet->getUid(), 0);

        $setsWithOverlap = $this->skillSetRepository->findOverlappingSets($skillSet, UserOrganisationsService::getOrganisationsOrEmpty($user));
        if ($setsWithOverlap->count()) {
            $this->calculateScoreWithRelatedSets($skillSet, $user, $setsWithOverlap);
        }
    }

    /**
     * @throws InvalidQueryException
     */
    public function calculateByUser(User $user): void
    {
        $this->recommendedSkillSetRepository->deleteRecommendations($user->getUid(), 0, 0);

        $organisationsOfUser = UserOrganisationsService::getOrganisationsOrEmpty($user);
        $skillSets = $this->skillSetRepository->findAllVisible($organisationsOfUser);
        foreach ($skillSets as $skillSet) {
            $this->calculateScoresBySourceSet($user, $skillSet);
        }
    }

    private function calculateScoreWithRelatedSets(SkillPath $skillSet, User $user, $setsWithOverlap): void
    {
        $verifications = $this->certificationRepository->findByGrantDateAndBrandsAndSkillSets(
            null,
            null,
            [],
            [$skillSet],
            $user
        );
        $completedSkillIds = array_map(function (Certification $c) {
            return $c->getSkill()->getUid();
        }, $verifications->toArray());
        $completedSkillIds = array_unique($completedSkillIds);
        $allSkillIds = $skillSet->getSkillIds();
        $missingSkillIds = array_diff($allSkillIds, $completedSkillIds);

        /** @var SkillPath $relatedSet */
        foreach ($setsWithOverlap as $relatedSet) {
            $B = $relatedSet->getSkillIds();

            $types = [
                ['A' => $missingSkillIds, 'type' => RecommendedSkillSetRepository::TYPE_MISSING],
                ['A' => $completedSkillIds, 'type' => RecommendedSkillSetRepository::TYPE_ACHIEVED],
            ];
            foreach ($types as $type) {
                $A = $type['A'];
                $intersectCount = count(array_intersect($A, $B));
                $mergeCount = count(array_unique(array_merge($A, $B)));
                if ($intersectCount) {
                    $jaccard = (float)$intersectCount / $mergeCount;
                    $this->recommendedSkillSetRepository->insertForSkillSet(
                        $type['type'],
                        $user,
                        $skillSet,
                        $relatedSet,
                        $jaccard * $relatedSet->getPopularityLog2(),
                        $jaccard
                    );
                }
            }
        }
    }

    /**
     * @throws InvalidQueryException
     * @throws Exception
     */
    public function calculateForSkill(User $user, Skill $skill): void
    {
        $this->recommendedSkillSetRepository->deleteForSkill($user, $skill);

        $setsWithOverlap = $this->skillSetRepository->findOverlappingBySkill($skill, UserOrganisationsService::getOrganisationsOrEmpty($user));
        if (!$setsWithOverlap->count()) {
            return;
        }

        $verifications = $this->certificationRepository->findBySkillsAndUser([$skill], $user, false);
        $completedSkillIds = array_map(function (Certification $c) {
            return $c->getSkill()->getUid();
        }, $verifications->toArray());
        $completedSkillIds = array_unique($completedSkillIds);
        $allSkillIds = [$skill->getUid()];
        $missingSkillIds = array_diff($allSkillIds, $completedSkillIds);

        /** @var SkillPath $relatedSet */
        foreach ($setsWithOverlap as $relatedSet) {
            $popularity = $this->recommendedSkillSetRepository->findPopularity($relatedSet);
            if (!$popularity) {
                continue;
            }
            $B = $relatedSet->getSkillIds();

            $types = [
                ['A' => $missingSkillIds, 'type' => RecommendedSkillSetRepository::TYPE_MISSING],
                ['A' => $completedSkillIds, 'type' =>  RecommendedSkillSetRepository::TYPE_ACHIEVED],
            ];
            foreach ($types as $type) {
                $A = $type['A'];
                $intersectCount = count(array_intersect($A, $B));
                $mergeCount = count(array_unique(array_merge($A, $B)));
                if ($intersectCount) {
                    $jaccard = $intersectCount / $mergeCount;
                    $this->recommendedSkillSetRepository->insertForSkill(
                        $type['type'],
                        $user,
                        $skill,
                        $relatedSet,
                        $jaccard * $popularity,
                        $jaccard
                    );
                }
            }
        }
    }

    public static function registerForUpdate(string $key, int $id): void
    {
        // todo add locking here
        $registry = GeneralUtility::makeInstance(Registry::class);
        $uids = $registry->get('skills', $key, []);
        $uids[] = $id;
        $registry->set('skills', $key, array_unique($uids));
    }

    public function wipe(): void
    {
        $this->recommendedSkillSetRepository->truncateRecommendations();
    }
}
