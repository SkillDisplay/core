<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\InvitationCode;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class InvitationCodeRepository extends BaseRepository
{
    public function findOneByCode(string $code): ?InvitationCode
    {
        $query = $this->createQuery();

        $result = $query->matching($query->equals('code', $code))->setLimit(1)->execute();
        if ($result instanceof QueryResultInterface) {
            /** @var InvitationCode $first */
            $first = $result->getFirst();
            return $first;
        }
        if (is_array($result)) {
            return $result[0] ?? null;
        }
        return null;
    }
}
