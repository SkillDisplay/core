<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Campaign;

class CampaignRepository extends BaseRepository
{
    /**
     * @param int $userId
     * @return Campaign[]
     */
    public function findByUserId(int $userId): array
    {
        $q = $this->createQuery();
        $q->matching($q->equals('user', $userId));
        return $q->execute()->toArray();
    }
}
