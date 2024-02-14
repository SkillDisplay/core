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

use Doctrine\DBAL\Driver\Exception;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for brands
 */
class BrandRepository extends BaseRepository
{
    public function findAll(): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->setOrderings([
            'name' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->execute();
    }

    /**
     * @param int $categoryId
     * @return Brand[]|QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findAllByCategory(int $categoryId): array|QueryResultInterface
    {
        $q = $this->createQuery();
        $q->matching($q->contains('categories', $categoryId));
        $q->setOrderings([
            'partner_level' => QueryInterface::ORDER_DESCENDING,
            'name' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->execute();
    }

    /**
     * @param Brand $brand
     * @return Brand[]
     * @throws Exception
     */
    public function findPatronsForBrand(Brand $brand): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'tx_skills_domain_model_brand'
        );
        $qb
            ->select('*')
            ->from('tx_skills_domain_model_brand', 'b')
            ->join('b', 'tx_skills_patron_mm', 'mm', 'b.uid = mm.uid_local')
            ->where(
                $qb->expr()->eq('mm.uid_foreign', $qb->createNamedParameter($brand->getUid(), Connection::PARAM_INT))
            )
            ->andWhere($qb->expr()->eq('b.sys_language_uid', 0));
        return $this->mapRows($qb->executeQuery()->fetchAllAssociative());
    }

    /**
     * @param SkillPath $path
     * @param int $level
     * @return Brand[]
     * @throws Exception
     */
    public function findVerifierBrandsForPath(SkillPath $path, int $level): array
    {
        $skillIds = array_map(function (Skill $skill) {
            return $skill->getUid();
        }, $path->getSkills()->toArray());
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'tx_skills_domain_model_brand'
        );
        $qb
            ->select('b.*')
            ->from('tx_skills_domain_model_brand', 'b')
            ->join('b', 'tx_skills_domain_model_certifier', 'c', 'b.uid = c.brand')
            ->join('c', 'tx_skills_domain_model_certifierpermission', 'p', 'c.uid = p.certifier')
            ->join('p', 'tx_skills_domain_model_skill', 's', 's.uid = p.skill')
            ->where($qb->expr()->in('s.uid', $skillIds))
            ->orderBy('b.name')
            ->groupBy('b.uid');
        if ($level) {
            $qb->andWhere($qb->expr()->eq('p.tier' . $level, 1));
        }
        return $this->mapRows($qb->executeQuery()->fetchAllAssociative());
    }

    /**
     * @return Brand[]
     * @throws Exception
     */
    public function findAllWithSkills(): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'tx_skills_domain_model_brand'
        );
        $qb
            ->select('b.*')
            ->from('tx_skills_domain_model_brand', 'b')
            ->join('b', 'tx_skills_skill_brand_mm', 'mm', 'mm.uid_foreign = b.uid')
            ->join('mm', 'tx_skills_domain_model_skill', 's', 'mm.uid_local = s.uid')
            ->where($qb->expr()->eq('b.deleted', 0))
            ->andWhere($qb->expr()->eq('s.deleted', 0))
            ->orderBy('b.name')
            ->groupBy('b.uid');
        return $this->mapRows($qb->executeQuery()->fetchAllAssociative());
    }

    /**
     * @return QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findAllWithMembers(): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->greaterThan('members', 0);
        $q->setOrderings([
            'name' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->execute();
    }

    /**
     * @param int $brandId
     * @return int
     * @throws Exception
     */
    public function getSkillCountForBrand(int $brandId): int
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'tx_skills_domain_model_brand'
        );
        return (int)$qb
            ->count('sb.uid_local')
            ->from('tx_skills_domain_model_brand', 'b')
            ->join('b', 'tx_skills_skill_brand_mm', 'sb', 'b.uid = sb.uid_foreign')
            ->where($qb->expr()->eq('b.uid', $brandId))
            ->executeQuery()->fetchFirstColumn();
    }

    /**
     * @param string $searchWord
     * @return Brand[]
     * @throws InvalidQueryException
     */
    public function findBySearchWord(string $searchWord): array
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
                $q->like('url', '%' . $searchWordLike . '%'),
            ];
            $constraints[] = $q->logicalOr(...$subConditions);
        }

        $constraints[] = $q->equals('show_in_search', '1');

        if (count($constraints) > 1) {
            $q->matching($q->logicalAnd(...$constraints));
        } else {
            $q->matching($constraints[0]);
        }
        return $q->execute()->toArray();
    }

    public function findByApiKey(string $apiKey): ?Brand
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectSysLanguage(false);
        $q->matching($q->equals('apiKey', $apiKey));
        /** @var Brand $brand */
        $brand = $q->execute()->getFirst();
        return $brand;
    }
}
