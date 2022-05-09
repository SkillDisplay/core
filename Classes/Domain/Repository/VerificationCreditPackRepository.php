<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\VerificationCreditPack;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

class VerificationCreditPackRepository extends BaseRepository
{
    /**
     * @param Brand $organisation
     * @return VerificationCreditPack[]
     * @throws InvalidQueryException
     */
    public function findAvailable(Brand $organisation): array
    {
        $q = $this->createQuery();
        $q->matching($q->logicalAnd([
            $q->equals('brand', $organisation),
            $q->greaterThan('currentPoints', 0),
            $q->lessThanOrEqual('valuta', $GLOBALS['EXEC_TIME']),
            $q->greaterThan('valid_thru', $GLOBALS['EXEC_TIME']),
        ]));
        $q->setOrderings([
            'validThru' => QueryInterface::ORDER_DESCENDING,
            'valuta' => QueryInterface::ORDER_ASCENDING,
        ]);
        $packs = $q->execute()->toArray();

        $q->matching($q->logicalAnd([
            $q->equals('brand', $organisation),
            $q->greaterThan('currentPoints', 0),
            $q->lessThanOrEqual('valuta', $GLOBALS['EXEC_TIME']),
            $q->equals('valid_thru', 0)
        ]));

        return array_merge($packs, $q->execute()->toArray());
    }

    public function getAvailableCredit(int $organisationId, \DateTime $date = null): int
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_skills_domain_model_verificationcreditpack');
        $result = $qb->addSelectLiteral('SUM(current_points) as points')
           ->from('tx_skills_domain_model_verificationcreditpack')
           ->where(
                $qb->expr()->eq('brand', $qb->createNamedParameter($organisationId, Connection::PARAM_INT)),
                $qb->expr()->gt('current_points', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->lte('valuta', $qb->createNamedParameter($date ? $date->getTimestamp() : $GLOBALS['EXEC_TIME'], Connection::PARAM_INT)),
                $qb->expr()->orX(
                    $qb->expr()->eq('valid_thru', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                    $qb->expr()->gt('valid_thru', $qb->createNamedParameter($date ? $date->getTimestamp() : $GLOBALS['EXEC_TIME'], Connection::PARAM_INT))
                ),
            )
            ->execute()->fetch();
        return (int)$result['points'];
    }

    public function findReceivedCredits(int $organisationId, \DateTime $from, \DateTime $to = null) {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_skills_domain_model_verificationcreditpack');
        $result = $qb->addSelectLiteral('SUM(initial_points) as points')
            ->from('tx_skills_domain_model_verificationcreditpack')
            ->where(
                $qb->expr()->eq('brand', $qb->createNamedParameter($organisationId, Connection::PARAM_INT)),
                $qb->expr()->gte('valuta', $qb->createNamedParameter($from->getTimestamp(), Connection::PARAM_INT)),
                $qb->expr()->lte('valuta', $qb->createNamedParameter($to ? $to->getTimestamp() : $GLOBALS['EXEC_TIME'], Connection::PARAM_INT)),
            )
            ->execute()->fetch();
        return (int)$result['points'];
    }
}
