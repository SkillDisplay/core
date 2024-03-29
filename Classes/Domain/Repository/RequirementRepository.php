<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2018 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Requirement;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

/**
 * The repository for Requirement
 */
class RequirementRepository extends BaseRepository
{
    /**
     * @param int $setSkillId
     * @return Requirement|null
     * @throws InvalidQueryException
     */
    public function findBySetSkillId(int $setSkillId): ?Requirement
    {
        $q = $this->createQuery();
        $q->matching($q->contains('sets.skills', $setSkillId));
        /** @var Requirement $requirement */
        $requirement = $q->execute()->getFirst();
        return $requirement;
    }
}
