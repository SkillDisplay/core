<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *           Georg Ringer
 **/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
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
        $q->getQuerySettings()->setRespectSysLanguage(false)->setIgnoreEnableFields(true);
        $q->matching($q->equals('uid', $uid));
        /** @var User $user */
        $user = $q->execute()->getFirst();
        return $user;
    }

    public function findByUsername(string $username): ?User
    {
        $q = $this->createQuery();
        $q->matching($q->equals('username', $username));
        /** @var User $user */
        $user = $q->execute()->getFirst();
        return $user;
    }

    public function getRawUser(User $user): array
    {
        $q = $this->createQuery();
        $q->getQuerySettings()
            ->setRespectSysLanguage(false)
            ->setIgnoreEnableFields(true);
        $q->matching($q->equals('uid', $user->getUid()));

        return $q->execute(true);
    }

    /**
     * @param Brand $brand
     * @return User[]|QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findManagers(Brand $brand): array|QueryResultInterface
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectSysLanguage(false);
        $q->matching($q->contains('managedBrands', $brand->getUid()));

        return $q->execute();
    }

    public function findByApiKey(string $apiKey): ?User
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectSysLanguage(false);
        $q->matching($q->equals('apiKey', $apiKey));
        /** @var User $user */
        $user = $q->execute()->getFirst();
        return $user;
    }

    /**
     * @param int $brandId
     * @return QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findByOrganisation(int $brandId): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->matching($q->contains('organisations', $brandId));
        return $q->execute();
    }
}
