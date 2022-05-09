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

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for brands
 */
class BrandRepository extends BaseRepository
{
    public function findAllByCategory(int $categoryId): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->matching($q->contains('categories', $categoryId));
        $q->setOrderings([
            'partner_level' => QueryInterface::ORDER_DESCENDING,
            'name' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->execute();
    }

    public function findPatronsForBrand(Brand $brand) : array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_skills_domain_model_brand');
        $rows = $qb
            ->select('*')
            ->from('tx_skills_domain_model_brand', 'b')
            ->join('b', 'tx_skills_patron_mm', 'mm', 'b.uid = mm.uid_local')
            ->where($qb->expr()->eq('mm.uid_foreign', $qb->createNamedParameter($brand->getUid(), Connection::PARAM_INT)))
            ->andWhere($qb->expr()->eq('b.sys_language_uid', 0))
            ->execute()->fetchAll();
        if ($rows) {
            $dataMapper = $this->objectManager->get(DataMapper::class);
            return $dataMapper->map(Brand::class, $rows);
        }
        return [];
    }

    public function findVerifierBrandsForPath(SkillPath $path, int $level) : array
    {
        $skillIds = array_map(function(Skill $skill) { return $skill->getUid();}, $path->getSkills()->toArray());
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_skills_domain_model_brand');
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
        $rows = $qb->execute()->fetchAll();
        if ($rows) {
            $dataMapper = $this->objectManager->get(DataMapper::class);
            return $dataMapper->map(Brand::class, $rows);
        }
        return [];
    }

    public function findAllWithSkills(): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_skills_domain_model_brand');
        $qb
            ->select('b.*')
            ->from('tx_skills_domain_model_brand', 'b')
            ->join('b', 'tx_skills_skill_brand_mm', 'mm', 'mm.uid_foreign = b.uid')
            ->join('mm', 'tx_skills_domain_model_skill', 's', 'mm.uid_local = s.uid')
            ->where($qb->expr()->eq('b.deleted', 0))
            ->andWhere($qb->expr()->eq('s.deleted', 0))
            ->orderBy('b.name')
            ->groupBy('b.uid');
        $rows = $qb->execute()->fetchAll();
        if ($rows) {
            $dataMapper = $this->objectManager->get(DataMapper::class);
            return $dataMapper->map(Brand::class, $rows);
        }
        return [];
    }

    public function findAllWithMembers(): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->greaterThan('members',0);
        $q->setOrderings([
            'name' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->execute();
    }

    public function getSkillCountForBrand(int $brandId): int
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_skills_domain_model_brand');
        return (int)$qb
            ->count('sb.uid_local')
            ->from('tx_skills_domain_model_brand', 'b')
            ->join('b', 'tx_skills_skill_brand_mm', 'sb', 'b.uid = sb.uid_foreign')
            ->where($qb->expr()->eq('b.uid', $brandId))
            ->execute()->fetchColumn();
    }

    public function findBySearchWord(string $searchWord) : array
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
            $constraints[] = $q->logicalOr($subConditions);
        }
        $constraints[] = $q->equals('show_in_search', '1');
        if (!empty($constraints)) {
            $q->matching($q->logicalAnd($constraints));
        }
        return $q->execute()->toArray();
    }

    public function findAllForPublicStats()
    {
        $q = $this->createQuery();
        $q->setOrderings([
            'name' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->execute();
    }

    public function findByApiKey(string $apiKey): ?Brand
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false)->setRespectSysLanguage(false);
        $q->matching($q->equals('apiKey', $apiKey));
        /** @var Brand $brand */
        $brand = $q->execute()->getFirst();
        return $brand;
    }

    public function findAllSortedAlphabetically() {
        $q = $this->createQuery();
        $q->setOrderings([
            'name' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->execute();
    }
}
