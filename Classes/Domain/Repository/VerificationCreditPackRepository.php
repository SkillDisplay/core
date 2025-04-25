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

use DateTime;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\VerificationCreditPack;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

class VerificationCreditPackRepository extends BaseRepository
{
    /**
     * @return VerificationCreditPack[]
     */
    public function findAvailable(Brand $organisation): array
    {
        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $q = $this->createQuery();
        $q->matching(
            $q->logicalAnd(
                $q->equals('brand', $organisation),
                $q->greaterThan('currentPoints', 0),
                $q->lessThanOrEqual('valuta', $now),
                $q->greaterThan('valid_thru', $now),
            )
        );
        $q->setOrderings([
            'validThru' => QueryInterface::ORDER_DESCENDING,
            'valuta' => QueryInterface::ORDER_ASCENDING,
        ]);
        /** @var VerificationCreditPack[] $packs */
        $packs = $q->execute()->toArray();

        $q->matching(
            $q->logicalAnd(
                $q->equals('brand', $organisation),
                $q->greaterThan('currentPoints', 0),
                $q->lessThanOrEqual('valuta', $now),
                $q->equals('valid_thru', 0),
            )
        );

        /** @var VerificationCreditPack[] $packs2 */
        $packs2 = $q->execute()->toArray();
        return array_merge($packs, $packs2);
    }

    public function getAvailableCredit(int $organisationId, ?DateTime $date = null): int
    {
        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'tx_skills_domain_model_verificationcreditpack'
        );
        $result = $qb->addSelectLiteral('SUM(current_points) as points')
            ->from('tx_skills_domain_model_verificationcreditpack')
            ->where(
                $qb->expr()->eq('brand', $qb->createNamedParameter($organisationId, Connection::PARAM_INT)),
                $qb->expr()->gt('current_points', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->lte(
                    'valuta',
                    $qb->createNamedParameter($date ? $date->getTimestamp() : $now, Connection::PARAM_INT)
                ),
                $qb->expr()->or(
                    $qb->expr()->eq('valid_thru', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                    $qb->expr()->gt(
                        'valid_thru',
                        $qb->createNamedParameter($date ? $date->getTimestamp() : $now, Connection::PARAM_INT)
                    )
                ),
            )
            ->executeQuery()->fetchAssociative();
        return (int)$result['points'];
    }

    public function findReceivedCredits(int $organisationId, DateTime $from, ?DateTime $to = null): int
    {
        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'tx_skills_domain_model_verificationcreditpack'
        );
        $result = $qb->addSelectLiteral('SUM(initial_points) as points')
            ->from('tx_skills_domain_model_verificationcreditpack')
            ->where(
                $qb->expr()->eq('brand', $qb->createNamedParameter($organisationId, Connection::PARAM_INT)),
                $qb->expr()->gte('valuta', $qb->createNamedParameter($from->getTimestamp(), Connection::PARAM_INT)),
                $qb->expr()->lte(
                    'valuta',
                    $qb->createNamedParameter($to ? $to->getTimestamp() : $now, Connection::PARAM_INT)
                ),
            )
            ->executeQuery()->fetchAssociative();
        return (int)$result['points'];
    }

    public function findByBrand(Brand $brand): QueryResultInterface
    {
        $query = $this->createQuery();
        return $query->matching($query->equals('brand', $brand))->execute();
    }
}
