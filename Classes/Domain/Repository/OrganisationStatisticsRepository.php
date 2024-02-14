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

use SkillDisplay\Skills\Domain\Model\OrganisationStatistics;

/**
 * The repository for awards
 */
class OrganisationStatisticsRepository extends BaseRepository
{
    public function getOrganisationStatisticsForBrand(int $brandId): ?OrganisationStatistics
    {
        $q = $this->createQuery();
        $q->matching(
            $q->equals('brand', $brandId)
        );
        /** @var OrganisationStatistics $stats */
        $stats = $q->execute()->getFirst();
        return $stats;
    }
}
