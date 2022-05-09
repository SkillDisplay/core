<?php declare(strict_types=1);
namespace SkillDisplay\Skills\Domain\Repository;

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 *
 ***/

use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;

/**
 * The repository for Shortlinks
 */
class ShortlinkRepository extends BaseRepository
{
    public function initializeObject()
    {
        $querySettings = $this->objectManager->get(QuerySettingsInterface::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }
}
