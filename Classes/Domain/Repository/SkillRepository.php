<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Skill;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for Skills
 */
class SkillRepository extends BaseRepository
{
    protected $defaultOrderings = [
        'sorting' => QueryInterface::ORDER_ASCENDING,
    ];

    public function findParents(Skill $skill): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'tx_skills_domain_model_skill'
        );
        $rows = $qb
            ->select('s.*')
            ->from('tx_skills_domain_model_skill', 's')
            ->join('s', 'tx_skills_domain_model_requirement', 'r', 's.uid = r.skill')
            ->join('r', 'tx_skills_domain_model_set', 'st', 'r.uid = st.requirement')
            ->join('st', 'tx_skills_domain_model_setskill', 'ss', 'st.uid = ss.tx_set')
            ->where(
                $qb->expr()->eq('ss.skill', $skill->getUid()),
                $qb->expr()->eq('s.sys_language_uid', 0)
            )
            ->orderBy('s.sorting')
            ->execute()->fetchAllAssociative();

        if ($rows) {
            $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
            return $dataMapper->map(Skill::class, $rows);
        }
        return [];
    }

    /**
     * @param int $brandId
     * @return QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findByBrand(int $brandId): QueryResultInterface
    {
        $q = $this->getQuery();
        $q->matching($q->contains('brands', $brandId));
        return $q->execute();
    }

    /**
     * @param int $tagId
     * @return QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findByTag(int $tagId): QueryResultInterface
    {
        $q = $this->getQuery();
        $q->matching(
            $q->logicalOr(
                $q->contains('tags', $tagId),
                $q->equals('domainTag', $tagId),
            )
        );
        return $q->execute();
    }

    /**
     * @param string $searchWord
     * @param array $organisationsOfUser
     * @return Skill[]
     * @throws InvalidQueryException
     */
    public function findBySearchWord(string $searchWord, array $organisationsOfUser): array
    {
        $q = $this->getQuery();
        $constraints = [];
        $searchWords = str_getcsv($searchWord, ' ');
        $searchWords = array_filter($searchWords);
        foreach ($searchWords as $searchWord) {
            // escape SQL like special chars
            $searchWordLike = addcslashes($searchWord, '_%');
            $subConditions = [
                $q->like('title', '%' . $searchWordLike . '%'),
                $q->like('description', '%' . $searchWordLike . '%'),
                $q->like('tags.title', '%' . $searchWordLike . '%'),
                $q->like('brands.name', '%' . $searchWordLike . '%'),
                $q->like('goals', '%' . $searchWordLike . '%'),

            ];
            $constraints[] = $q->logicalOr(...$subConditions);
        }

        $constraints[] = $q->logicalOr(...$this->getVisibilityConditions($q, $organisationsOfUser));

        if (count($constraints) > 1) {
            $q->matching($q->logicalAnd(...$constraints));
        } else {
            $q->matching($constraints[0]);
        }
        return $q->execute()->toArray();
    }

    /**
     * returns conditions for the visibility of a skill
     *
     * @param QueryInterface $q
     * @param Brand[] $organisationsOfUser
     * @return array
     */
    private function getVisibilityConditions(QueryInterface $q, array $organisationsOfUser): array
    {
        $conditions = [];

        $conditions[] = $q->equals('visibility', Skill::VISIBILITY_PUBLIC);
        if ($organisationsOfUser) {
            $brandIds = array_map(function (Brand $b) {
                return $b->getUid();
            }, $organisationsOfUser);
            try {
                $conditions[] = $q->logicalAnd(
                    $q->in('brands.uid', $brandIds),
                    $q->equals('visibility', Skill::VISIBILITY_ORGANISATION),
                );
            } catch (InvalidQueryException) {
            }
        }

        return $conditions;
    }
}
