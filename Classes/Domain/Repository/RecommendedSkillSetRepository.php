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

namespace SkillDisplay\Skills\Domain\Repository;

use Doctrine\DBAL\Driver\Exception;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Service\UserOrganisationsService;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Repository;

class RecommendedSkillSetRepository extends Repository
{
    public const TYPE_MISSING = 0;
    public const TYPE_ACHIEVED = 1;

    public function deleteRecommendations(int $user, int $sourceSet, int $relatedSet): void
    {
        $condition = [];
        if ($user) {
            $condition['user'] = $user;
        }
        if ($sourceSet) {
            $condition['source_skillset'] = $sourceSet;
        }
        if ($relatedSet) {
            $condition['recommended_skillset'] = $relatedSet;
        }
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_recommendedskillset'
        );
        $con->delete('tx_skills_domain_model_recommendedskillset', $condition);
    }

    public function deleteForSkill(User $user, Skill $skill): void
    {
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_recommendedskillset'
        );
        $con->delete('tx_skills_domain_model_recommendedskillset', [
            'user' => $user->getUid(),
            'source_skill' => $skill->getUid(),
        ]);
    }

    public function insertForSkillSet(
        int $type,
        User $user,
        SkillPath $skillSet,
        SkillPath $recommendedSkillSet,
        float $score,
        float $jaccard
    ): void {
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_recommendedskillset'
        );
        $con->insert(
            'tx_skills_domain_model_recommendedskillset',
            [
                'type' => $type,
                'user' => $user->getUid(),
                'source_skillset' => $skillSet->getUid(),
                'recommended_skillset' => $recommendedSkillSet->getUid(),
                'jaccard' => $jaccard,
                'score' => $score,
            ]
        );
    }

    public function insertForSkill(
        int $type,
        User $user,
        Skill $skill,
        SkillPath $recommendedSkillSet,
        float $score,
        float $jaccard
    ): void {
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_recommendedskillset'
        );
        $con->insert(
            'tx_skills_domain_model_recommendedskillset',
            [
                'type' => $type,
                'user' => $user->getUid(),
                'source_skill' => $skill->getUid(),
                'recommended_skillset' => $recommendedSkillSet->getUid(),
                'jaccard' => $jaccard,
                'score' => $score,
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function findBySkillSet(User $user, SkillPath $set): array
    {
        $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_recommendedskillset'
        );

        $userBrands = UserOrganisationsService::getOrganisationsOrEmpty($user);

        $recommendations = [];
        foreach ([static::TYPE_MISSING, static::TYPE_ACHIEVED] as $type) {
            foreach ([1, 2, 3, 4] as $level) {
                $qb = $con->createQueryBuilder();
                $onRecom = (string)$qb->expr()->andX(
                    $qb->expr()->eq('r.user', $user->getUid()),
                    $qb->expr()->eq('r.type', $type),
                    $qb->expr()->eq('r.source_skillset', $set->getUid()),
                    $qb->expr()->eq('r.recommended_skillset', $qb->quoteIdentifier('s.uid')),
                );
                $onCatmm = (string)$qb->expr()->andX(
                    $qb->expr()->eq('mm.tablenames', $qb->createNamedParameter('tx_skills_domain_model_skillpath')),
                    $qb->expr()->eq('mm.fieldname', $qb->createNamedParameter('categories')),
                    $qb->expr()->eq('mm.uid_foreign', $qb->quoteIdentifier('s.uid'))
                );
                $onCat = (string)$qb->expr()->andX(
                    $qb->expr()->eq('c.description', $qb->createNamedParameter($level)),
                    $qb->expr()->eq('mm.uid_local', $qb->quoteIdentifier('c.uid'))
                );
                $onBrand = (string)$qb->expr()->eq('bmm.uid_local', $qb->quoteIdentifier('s.uid'));
                $qb->select('s.*')
                    ->from('tx_skills_domain_model_skillpath', 's')
                    ->join('s', 'tx_skills_domain_model_recommendedskillset', 'r', $onRecom)
                    ->join('s', 'tx_skills_skillset_brand_mm', 'bmm', $onBrand)
                    ->join('s', 'sys_category_record_mm', 'mm', $onCatmm)
                    ->join('mm', 'sys_category', 'c', $onCat)
                    ->orderBy('r.score', 'DESC')
                    ->setMaxResults(3);
                $visibilityConstraints = [];
                $visibilityConstraints[] = $qb->expr()->eq('s.visibility', $qb->createNamedParameter(SkillPath::VISIBILITY_PUBLIC, Connection::PARAM_INT));
                if ($userBrands) {
                    // more or less a copy of SkillPathRepository::getVisibilityConditions
                    $brandIds = array_map(function (Brand $b) {
                        return $b->getUid();
                    }, $userBrands);
                    $visibilityConstraints[] = $qb->expr()->andX(
                        $qb->expr()->eq('s.visibility', $qb->createNamedParameter(SkillPath::VISIBILITY_ORGANISATION, Connection::PARAM_INT)),
                        $qb->expr()->in('bmm.uid_foreign', $qb->createNamedParameter($brandIds, $con::PARAM_INT_ARRAY))
                    );
                }
                $qb->where($qb->expr()->orX(...$visibilityConstraints));
                $rows = $qb->executeQuery()->fetchAllAssociative();
                $sets = $dataMapper->map(SkillPath::class, $rows);
                $recommendations[] = [
                    'type' => $type,
                    'level' => $level,
                    'sets' => $sets,
                ];
            }
        }
        return $recommendations;
    }

    /**
     * @throws Exception
     */
    public function findBySkill(User $user, Skill $skill): array
    {
        $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_recommendedskillset'
        );

        $recommendations = [];
        return $recommendations;
        foreach ([static::TYPE_MISSING, static::TYPE_ACHIEVED] as $type) {
            foreach ([1, 2, 3, 4] as $level) {
                $qb = $con->createQueryBuilder();
                $on = (string)$qb->expr()->andX(
                    $qb->expr()->eq('r.user', $user->getUid()),
                    $qb->expr()->eq('r.type', $type),
                    $qb->expr()->eq('r.source_skill', $skill->getUid()),
                    $qb->expr()->eq('r.recommended_skillset', $qb->quoteIdentifier('s.uid')),
                );
                // todo $level needs to be validated
                $rows = $qb->select('s.*')
                    ->from('tx_skills_domain_model_skillpath', 's')
                    ->join('s', 'tx_skills_domain_model_recommendedskillset', 'r', $on)
                    ->orderBy('r.score', 'DESC')
                    ->setMaxResults(5)
                    ->executeQuery()->fetchAllAssociative();
                $sets = $dataMapper->map(SkillPath::class, $rows);
                $recommendations[] = [
                    'type' => $type,
                    'sets' => $sets,
                ];
            }
        }
        return $recommendations;
    }

    public function addPopularity(SkillPath $skillSet, float $log2): void
    {
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_skillpath'
        );
        $con->insert('tx_skills_domain_model_skillpath', [
            'uid' => $skillSet->getUid(),
            'popularity_log2' => $log2,
        ]);
    }

    /**
     * @throws Exception
     */
    public function findPopularity(SkillPath $skillSet): float
    {
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_skillpath'
        );
        return (float)($con->select(['popularity_log2'], 'tx_skills_domain_model_skillpath', [
            'uid' => $skillSet->getUid(),
        ])->fetchOne());
    }

    /**
     * Update all scores of recommendations concerning the given SkillSet
     *
     * @param SkillPath $recommendedSkillSet
     * @throws \Doctrine\DBAL\Exception
     */
    public function recalculateScore(SkillPath $recommendedSkillSet): void
    {
        $sql = 'UPDATE tx_skills_domain_model_recommendedskillset SET score = jaccard * %.20F WHERE recommended_skillset = %d';
        $sql = sprintf($sql, $recommendedSkillSet->getPopularityLog2(), $recommendedSkillSet->getUid());
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_recommendedskillset'
        );
        $con->executeStatement($sql);
    }

    public function updateSkillSetPopularity(SkillPath $skillSet): void
    {
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_skillpath'
        );
        $con->update(
            'tx_skills_domain_model_skillpath',
            [
                'popularity_log2' => $skillSet->getPopularityLog2(),
            ],
            [
                'uid' => $skillSet->getUid(),
            ]
        );
    }

    public function truncateRecommendations(): void
    {
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_recommendedskillset'
        );
        $con->truncate('tx_skills_domain_model_recommendedskillset');
    }
}
