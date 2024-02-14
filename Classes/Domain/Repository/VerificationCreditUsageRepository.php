<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\VerificationCreditUsage;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class VerificationCreditUsageRepository extends BaseRepository
{
    /**
     * @param Certification $certification
     * @return VerificationCreditUsage[]|QueryResultInterface
     */
    public function findByVerification(Certification $certification): array|QueryResultInterface
    {
        $query = $this->createQuery();
        return $query->matching($query->equals('verification', $certification))->execute();
    }
}
