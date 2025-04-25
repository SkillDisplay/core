<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) Reelworx GmbH and Georg Ringer
 *
 */

namespace SkillDisplay\Skills\Domain\Repository;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @template T of DomainObjectInterface
 * @extends Repository<T>
 */
class BaseRepository extends Repository
{
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(QuerySettingsInterface::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * return QueryResultInterface<int,T>
     */
    public function findAll(): QueryResultInterface
    {
        return $this->getQuery()->execute();
    }

    /**
     * @phpstan-return QueryInterface<T>
     */
    protected function getQuery(): QueryInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        return $query;
    }

    /**
     * @phpstan-return list<T>
     */
    protected function mapRows(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $dataMapper = GeneralUtility::makeInstance(DataMapper::class);
        return $dataMapper->map($this->objectType, $rows);
    }
}
