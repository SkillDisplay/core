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
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for SkillSets
 */
class SkillPathRepository extends BaseRepository
{
    protected $defaultOrderings = [
        'name' => QueryInterface::ORDER_ASCENDING,
    ];

    /**
     * @param Skill $skill
     * @return SkillPath[]
     * @throws InvalidQueryException
     */
    public function findBySkill(Skill $skill): array
    {
        $q = $this->createQuery();
        $q->matching($q->contains('skills', $skill->getUid()));
        return $q->execute()->toArray();
    }

    /**
     * @param string $searchWord
     * @param Brand[] $organisationsOfUser
     * @return array
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
                $q->like('name', '%' . $searchWordLike . '%'),
                $q->like('description', '%' . $searchWordLike . '%'),
                $q->like('brands.name', '%' . $searchWordLike . '%'),
                $q->like('skills.title', '%' . $searchWordLike . '%'),
                $q->like('skills.domainTag', '%' . $searchWordLike . '%'),
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
     * @param Brand[] $organisationsOfUser
     * @return array
     */
    public function findAllVisible(array $organisationsOfUser): array
    {
        $q = $this->createQuery();
        $conditions = $this->getVisibilityConditions($q, $organisationsOfUser);
        $q->matching($q->logicalOr(...$conditions));

        return $q->execute()->toArray();
    }

    public function findSkillPathsOfBrand(int $brandUid): array
    {
        $q = $this->createQuery();
        $q->matching(
            $q->equals('brands.uid', $brandUid)
        );
        $q->setOrderings([
            'name' => QueryInterface::ORDER_ASCENDING,
            'uid' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->execute()->toArray();
    }

    public function getSkillsForSyllabusDownload(SkillPath $skillSet): array
    {
        $skills = [];
        foreach ($skillSet->getSkills() as $skill) {
            $skills[$skill->getDomainTag() ? $skill->getDomainTag()->getTitle() : '-'][] = $skill;
        }
        if ($skills['-']) {
            $noDomainSkills = $skills['-'];
            unset($skills['-']);
            $skills['-'] = $noDomainSkills;
        }
        return $skills;
    }

    public function getSkillsForCompleteDownload(SkillPath $skillSet): array
    {
        $skills = [];
        /** @var Skill $skill */
        foreach ($skillSet->getSkills() as $skill) {
            $skills[] = $skill;
        }
        usort($skills, function (Skill $a, Skill $b) {
            return $a->getTitle() <=> $b->getTitle();
        });
        return $skills;
    }

    /**
     * returns conditions for the visibility of a skill set
     *
     * also done in \SkillDisplay\Skills\Domain\Repository\RecommendedSkillSetRepository::findBySkillSet
     *
     * @param QueryInterface $q
     * @param Brand[] $organisationsOfUser
     * @return array
     */
    private function getVisibilityConditions(QueryInterface $q, array $organisationsOfUser): array
    {
        $conditions = [];

        $conditions[] = $q->equals('visibility', SkillPath::VISIBILITY_PUBLIC);
        if ($organisationsOfUser) {
            try {
                $brandIds = array_map(function (Brand $b) {
                    return $b->getUid();
                }, $organisationsOfUser);
                $conditions[] = $q->logicalAnd(
                    $q->in('brands.uid', $brandIds),
                    $q->equals('visibility', SkillPath::VISIBILITY_ORGANISATION),
                );
            } catch (InvalidQueryException) {
            }
        }

        return $conditions;
    }

    /**
     * Find all skillsets with overlapping skills with set origin, but exclude origin
     *
     * @param SkillPath $origin
     * @param array $organisationsOfUser
     * @return array|QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findOverlappingSets(SkillPath $origin, array $organisationsOfUser): QueryResultInterface|array
    {
        $skillIds = array_map(function (Skill $s) {
            return $s->getUid();
        }, $origin->getSkills()->toArray());
        $q = $this->createQuery();
        $q->matching(
            $q->logicalAnd(
                $q->in('skills.uid', $skillIds),
                $q->logicalNot($q->equals('uid', $origin->getUid())),
                $q->logicalNot(
                    $q->equals(
                        'categories.uid',
                        $origin->getFirstCategory() ? $origin->getFirstCategory()->getUid() : 0
                    )
                ),
                $q->logicalOr(...$this->getVisibilityConditions($q, $organisationsOfUser)),
            )
        );
        return $q->execute();
    }

    /**
     * Find all skillsets with overlapping skill
     *
     * @param Skill $origin
     * @param array $organisationsOfUser
     * @return array|QueryResultInterface
     */
    public function findOverlappingBySkill(Skill $origin, array $organisationsOfUser): QueryResultInterface|array
    {
        $q = $this->createQuery();
        $q->matching(
            $q->logicalAnd(
                $q->equals('skills.uid', $origin->getUid()),
                $q->logicalOr(...$this->getVisibilityConditions($q, $organisationsOfUser)),
            )
        );
        return $q->execute();
    }
}
