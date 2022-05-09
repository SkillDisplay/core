<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *           Georg Ringer
 *
 ***/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for Users
 */
class UserRepository extends BaseRepository
{
    public function findAllPublished(): QueryResultInterface
    {
        $query = $this->getQuery();
        return $query
            ->matching($query->equals('publishSkills', 1))
            ->setOrderings([
                'lastName' => QueryInterface::ORDER_ASCENDING,
                'firstName' => QueryInterface::ORDER_ASCENDING,
            ])
            ->execute();
    }

    public function findDisabledByUid(int $uid): ?User
    {
        $q = $this->createQuery();
        $qs = $q->getQuerySettings();
        $qs->setRespectSysLanguage(false);
        $qs->setRespectStoragePage(false);
        $qs->setIgnoreEnableFields(true);
        $q->matching($q->equals('uid', $uid));
        /** @var User $user */
        $user = $q->execute()->getFirst();
        return $user;
    }

    public function findByUsername(string $username): ?User
    {
        $q = $this->createQuery();
        $qs = $q->getQuerySettings();
        $qs->setRespectStoragePage(false);
        $q->matching($q->equals('username', $username));
        /** @var User $user */
        $user = $q->execute()->getFirst();
        return $user;
    }

    public function getRawUser(User $user) : array
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false)->setRespectSysLanguage(false)->setIgnoreEnableFields(true);
        $q->matching($q->equals('uid', $user->getUid()));

        return $q->execute(true);
    }

    public function findManagers(Brand $brand): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false)->setRespectSysLanguage(false);
        $q->matching($q->contains('managedBrands', $brand->getUid()));

        return $q->execute();
    }

    public function findLatestVerificationsBySkill(Skill $skill, int $limit) : array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_skills_domain_model_skill');
        $rows = $qb
            ->select('u.*')
            ->from('fe_users', 'u')
            ->join('u', 'tx_skills_domain_model_certification', 'c', 'c.user = u.uid AND c.skill = ' . $skill->getUid())
            ->where('u.publish_skills = 1')
            ->andWhere('u.deleted = 0')
            ->andWhere('u.disable = 0')
            ->andWhere($qb->expr()->isNotNull('c.grant_date'))
            ->andWhere($qb->expr()->isNull('c.revoke_date'))
            ->orderBy('c.grant_date', 'DESC')
            ->orderBy('u.uid', 'ASC')
            ->groupBy('u.uid')
            ->setMaxResults($limit)
            ->execute()->fetchAll();
        if ($rows) {
            $dataMapper = $this->objectManager->get(DataMapper::class);
            return $dataMapper->map(User::class, $rows);
        }
        return [];
    }

    public function findByApiKey(string $apiKey): ?User
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false)->setRespectSysLanguage(false);
        $q->matching($q->equals('apiKey', $apiKey));
        /** @var User $user */
        $user = $q->execute()->getFirst();
        return $user;
    }

    public function findByOrganisation(int $brandId): QueryResultInterface
    {
        $q = $this->createQuery();
        $qs = $q->getQuerySettings();
        $qs->setRespectStoragePage(false);
        $q->matching($q->contains('organisations', $brandId));
        return $q->execute();
    }
}
