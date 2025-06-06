<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for Verifiers
 */
class CertifierRepository extends BaseRepository
{
    public function findBySkillAndTier(Skill $skill, int $tier): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->matching(
            $q->logicalAnd(
                $q->equals('permissions.skill', $skill->getUid()),
                $q->equals('permissions.tier' . $tier, 1),
            )
        );
        return $q->execute();
    }

    public function findByBrandId(int $brandId): QueryResultInterface
    {
        $q = $this->createQuery();
        $q->matching(
            $q->equals('brand', $brandId)
        );
        return $q->execute();
    }

    public function findByUser(User $user): QueryResultInterface
    {
        $query = $this->createQuery();
        return $query->matching($query->equals('User', $user))->execute();
    }
}
