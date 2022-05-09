<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;

class CampaignRepository extends BaseRepository
{
    public function initializeObject()
    {
        $querySettings = $this->objectManager->get(QuerySettingsInterface::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    public function findByUserId(int $userId): array
    {
        $q = $this->createQuery();
        $q->matching($q->logicalAnd(
            [
                $q->equals('user', $userId),
            ]
        ));
        return $q->execute()->toArray();
    }
}
