<?php declare(strict_types=1);

/*
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * Copyright (c) Reelworx GmbH and Georg Ringer
 *
 */

namespace SkillDisplay\Skills\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class BaseRepository extends Repository
{
    public function initializeObject()
    {
        $this->defaultQuerySettings = $this->objectManager->get(QuerySettingsInterface::class);
        $this->defaultQuerySettings->setRespectStoragePage(false);
    }

    public function findAll() : QueryResultInterface
    {
        return $this->getQuery()->execute();
    }

    protected function getQuery() : QueryInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);

        return $query;
    }
}
