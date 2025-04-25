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

namespace SkillDisplay\Skills\Service;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Statement;
use Exception;
use SkillDisplay\Skills\Domain\Model\Award;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class StatisticsService
{
    private const array RANK_ASSIGNMENT = [
        3 => 95,
        2 => 75,
        1 => 50,
        0 => 25,
    ];

    protected QueryBuilder $qbAwards;

    public function __construct(
        protected readonly BrandRepository $brandRepository,
        protected readonly UserRepository $userRepository,
        protected readonly CertifierRepository $certifierRepository,
        protected readonly CertificationRepository $certificationRepository,
        protected readonly SkillPathRepository $skillSetRepository,
        protected readonly SkillRepository $skillRepository,
        protected readonly PersistenceManager $persistenceManager,
        protected readonly ConnectionPool $connectionPool,
    ) {}

    public function run(): void
    {
        $connection = $this->connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $this->qbAwards = $connection->createQueryBuilder();

        $connection->truncate('tx_skills_domain_model_award');

        // Verified Award Calculation
        $this->calculateVerifiedAwards();
        // Member Award Calculation
        $this->calculateMemberAwards();
        // Coach Award Calculation
        $this->calculateCoachMentorAwards(Award::TYPE_COACH);
        // Mentor Award Calculation
        $this->calculateCoachMentorAwards(Award::TYPE_MENTOR);
    }

    private function calculateVerifiedAwards(): void
    {
        $users = $this->userRepository->findAll();
        /** @var User $user */
        foreach ($users as $user) {
            $qbCertifications = $this->connectionPool->getQueryBuilderForTable('tx_skills_domain_model_certification');
            $res = $qbCertifications
                ->select('s.uid as skill_id', 'sb.uid_foreign as brand', 'c.tier1', 'c.tier2', 'c.tier3', 'c.tier4')
                ->from('tx_skills_domain_model_certification', 'c')
                ->join('c', 'tx_skills_domain_model_skill', 's', 'c.skill = s.uid')
                ->join('s', 'tx_skills_skill_brand_mm', 'sb', 's.uid = sb.uid_local')
                ->where(
                    $qbCertifications->expr()->eq('c.user', $qbCertifications->createPositionalParameter($user->getUid())),
                    $qbCertifications->expr()->isNotNull('c.grant_date'),
                    $qbCertifications->expr()->isNull('c.revoke_date')
                )
                ->executeQuery();

            $groupedByBrandAndTier = [];
            while ($cert = $res->fetchAssociative()) {
                $level = $cert['tier1'] ? 1 : ($cert['tier2'] ? 2 : ($cert['tier3'] ? 3 : ($cert['tier4'] ? 4 : 0)));
                if ($level) {
                    $groupedByBrandAndTier[$cert['brand']][$level][] = $cert;
                }
            }

            $res->free();

            foreach ($groupedByBrandAndTier as $brandId => $tiersOfBrand) {
                $skillCountOfBrand = $this->brandRepository->getSkillCountForBrand((int)$brandId);
                if (!$skillCountOfBrand) {
                    continue;
                }
                foreach ($tiersOfBrand as $level => $certificates) {
                    $percentage = count($certificates) * 100 / $skillCountOfBrand;
                    $achievedRank = -1;
                    foreach (self::RANK_ASSIGNMENT as $rank => $limit) {
                        if ($percentage >= $limit) {
                            $achievedRank = $rank;
                            break;
                        }
                    }
                    if ($achievedRank >= 0) {
                        $this->qbAwards->insert('tx_skills_domain_model_award')
                            ->values([
                                'user' => $user->getUid(),
                                'brand' => $brandId,
                                'type' => Award::TYPE_VERIFICATIONS,
                                'level' => $level,
                                'rank' => $achievedRank,
                            ])
                            ->executeStatement();
                    }
                }
            }
        }
    }

    private function calculateMemberAwards(): void
    {
        $brands = $this->brandRepository->findAllWithMembers();
        /** @var Brand $brand */
        foreach ($brands as $brand) {
            $memberRanking = [];
            $brandId = $brand->getUid();
            foreach ($brand->getMembers() as $member) {
                $qbCertifications = $this->connectionPool->getQueryBuilderForTable('tx_skills_domain_model_certification');
                $statement = $qbCertifications
                    ->select('c.uid as uid', 's.uid as skill_id', 'c.user as user_id', 'c.tier1', 'c.tier2', 'c.tier3', 'c.tier4')
                    ->from('tx_skills_domain_model_certification', 'c')
                    ->join('c', 'tx_skills_domain_model_skill', 's', 'c.skill = s.uid')
                    ->where(
                        $qbCertifications->expr()->eq('c.user', $qbCertifications->createPositionalParameter($member->getUid(), Connection::PARAM_INT)),
                        $qbCertifications->expr()->isNotNull('c.grant_date'),
                        $qbCertifications->expr()->isNull('c.revoke_date')
                    )
                    ->executeQuery();
                $certs = $statement->fetchAllAssociative();
                $statement->free();

                $skillPoints = 0;
                foreach ($certs as $cert) {
                    $skillPoints += self::getSkillPoints($cert);
                }
                $memberRanking[$member->getUid()] = $skillPoints;
            }
            if (count($memberRanking) > 0) {
                $this->createAwards($memberRanking, Award::TYPE_MEMBER, $brandId);
            }
        }
    }

    private function calculateCoachMentorAwards(int $type): void
    {
        // If type == 2 / Coach
        // If type == 3 / Mentor
        $tierField = $type === Award::TYPE_COACH ? 'tier2' : 'tier4';

        $brands = $this->brandRepository->findAllWithMembers();
        /** @var Brand $brand */
        foreach ($brands as $brand) {
            $brandId = $brand->getUid();
            $certifiers = $this->certifierRepository->findByBrandId($brandId);
            $ranking = [];
            /** @var Certifier $certifier */
            foreach ($certifiers as $certifier) {
                if (!$certifier->getUser()) {
                    continue;
                }
                $certifierId = $certifier->getUid();
                $qbCertifications = $this->connectionPool->getQueryBuilderForTable('tx_skills_domain_model_certification');
                $statement = $qbCertifications
                    ->count('c.uid')
                    ->from('tx_skills_domain_model_certification', 'c')
                    ->join('c', 'tx_skills_domain_model_skill', 's', 'c.skill = s.uid')
                    ->join('s', 'tx_skills_skill_brand_mm', 'sb', 's.uid = sb.uid_local')
                    ->where(
                        $qbCertifications->expr()->eq('sb.uid_foreign', $qbCertifications->createPositionalParameter($brandId, Connection::PARAM_INT)),
                        $qbCertifications->expr()->eq('c.certifier', $qbCertifications->createPositionalParameter($certifierId, Connection::PARAM_INT)),
                        $qbCertifications->expr()->eq($tierField, 1),
                        $qbCertifications->expr()->isNotNull('c.grant_date'),
                        $qbCertifications->expr()->isNull('c.revoke_date')
                    )
                    ->executeQuery();
                $ranking[$certifier->getUser()->getUid()] = $statement->fetchOne();
                $statement->free();
            }
            if (count($ranking) > 0) {
                $this->createAwards($ranking, $type, $brandId);
            }
        }
    }

    protected function createAwards(array $ranking, int $type, int $brandId): void
    {
        asort($ranking);
        $percentageSpace = 100 / count($ranking);
        $i = 1;
        foreach ($ranking as $user => $count) {
            if ($count > 0) {
                $achievedRank = -1;
                foreach (self::RANK_ASSIGNMENT as $rank => $limit) {
                    if ($i * $percentageSpace >= $limit) {
                        $achievedRank = $rank;
                        break;
                    }
                }
                if ($achievedRank >= 0) {
                    $this->qbAwards->insert('tx_skills_domain_model_award')
                        ->values([
                            'user' => $user,
                            'brand' => $brandId,
                            'type' => $type,
                            'level' => Skill::LevelTierMap['undefined'],
                            'rank' => $achievedRank,
                        ])->executeStatement();
                }
            }
            $i++;
        }
    }

    public static function getSkillPoints(array $cert): int
    {
        if ($cert['tier1']) {
            return 4;
        }
        if ($cert['tier3']) {
            return 1;
        }
        return 2;
    }

    public function calculateOrganisationStatistics(): void
    {
        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'full');

        $connection = $this->connectionPool->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $qbStatistics = $connection->createQueryBuilder();

        $connection->truncate('tx_skills_domain_model_organisationstatistics');

        $statementVerifications = $this->initCertificationsStatement();

        $brands = $this->brandRepository->findAllWithMembers();
        $skillSets = $this->skillSetRepository->findAll();

        /** @var Brand $brand */
        foreach ($brands as $brand) {
            $brandId = $brand->getUid();

            $certs = [];
            $totalScore = 0;
            $currentMonthUsers = 0;
            $lastMonthUsers = 0;
            $interests = [];
            $potential = [
                'tier1' => [
                    'total' => 0,
                    'verified' => 0,
                    'potential' => 0,
                ],
                'tier2' => [
                    'total' => 0,
                    'verified' => 0,
                    'potential' => 0,
                ],
                'tier3' => [
                    'total' => 0,
                    'verified' => 0,
                    'potential' => 0,
                ],
                'tier4' => [
                    'total' => 0,
                    'verified' => 0,
                    'potential' => 0,
                ],
            ];

            $userIds = $this->getAllUserIdsForBrand($brandId);
            foreach ($userIds as $member) {
                $potentialIds = [
                    'tier1' => [
                        'total' => [],
                        'verified' => [],
                        'potential' => [],
                    ],
                    'tier2' => [
                        'total' => [],
                        'verified' => [],
                        'potential' => [],
                    ],
                    'tier3' => [
                        'total' => [],
                        'verified' => [],
                        'potential' => [],
                    ],
                    'tier4' => [
                        'total' => [],
                        'verified' => [],
                        'potential' => [],
                    ],
                ];

                $statementVerifications->bindValue(1, $member['user']);
                $statementVerifications->bindValue(2, $brandId);
                $result = $statementVerifications->executeQuery();
                $memberCerts = $result->fetchAllAssociative();
                $result->free();

                $userIsActiveCurrent = false;
                $userIsActiveLast = false;
                $setsGroupedByTier = [];
                foreach ($memberCerts as $cert) {
                    $certs[] = $cert;

                    // Calculate active users
                    $date = new DateTime($cert['grant_date']);
                    $diff = $now->diff($date);
                    if ($diff->days <= 30) {
                        $userIsActiveCurrent = true;
                    } elseif ($diff->days <= 60) {
                        $userIsActiveLast = true;
                    }

                    /** @var ?Skill $skill */
                    $skill = $this->skillRepository->findByUid($cert['skill_id']);
                    if (!$skill) {
                        continue;
                    }

                    foreach ($this->skillSetRepository->findBySkill($skill) as $skillSet) {
                        $level = $cert['tier1'] ? 1 : ($cert['tier2'] ? 2 : ($cert['tier3'] ? 3 : ($cert['tier4'] ? 4 : 0)));
                        if ($level) {
                            if (!isset($setsGroupedByTier['tier' . $level][$skillSet->getUid()]['verified_count'])) {
                                $setsGroupedByTier['tier' . $level][$skillSet->getUid()]['verified_count'] = 0;
                            }
                            $setsGroupedByTier['tier' . $level][$skillSet->getUid()]['verified_count']++;
                            $setsGroupedByTier['tier' . $level][$skillSet->getUid()]['skill_ids'][] = $skill->getUid();
                        }
                    }
                }
                if ($userIsActiveCurrent) {
                    $currentMonthUsers++;
                }
                if ($userIsActiveLast) {
                    $lastMonthUsers++;
                }

                foreach ($setsGroupedByTier as $level => $sets) {
                    foreach ($sets as $setId => $set) {
                        /** @var SkillPath $setObject */
                        $setObject = $this->skillSetRepository->findByUid($setId);
                        $setSkills = $setObject->getSkillIds();
                        $setSkillCount = $setObject->getSkillCount();
                        $verifiedCount = $set['verified_count'];
                        if ($verifiedCount == $setSkillCount) {
                            break;
                        }
                        foreach ($setSkills as $skillId) {
                            $potentialIds[$level]['total'][] = $skillId;
                        }
                        foreach ($set['skill_ids'] as $skillId) {
                            $potentialIds[$level]['verified'][] = $skillId;
                            /** @var ?Skill $skill */
                            $skill = $this->skillRepository->findByUid($skillId);
                            if (!$skill) {
                                continue;
                            }
                            foreach ($skill->getSuccessorSkills() as $successor) {
                                $potentialIds[$level]['potential'][] = $successor->getUid();
                            }
                        }
                    }
                }

                foreach ($potentialIds as $level => $stats) {
                    $potentialIds[$level]['total'] = array_unique($stats['total']);
                    $potentialIds[$level]['verified'] = array_unique($stats['verified']);
                    $potentialIds[$level]['potential'] = array_unique($stats['potential']);

                    $potential[$level]['total'] += count($potentialIds[$level]['total']);
                    $potential[$level]['verified'] += count($potentialIds[$level]['verified']);
                    $potential[$level]['potential'] += count($potentialIds[$level]['potential']);
                }

                // Member interests
                foreach ($skillSets as $skillSet) {
                    $memberSkillCount = 0;
                    $skillIds = $skillSet->getSkillIds();
                    foreach ($memberCerts as $cert) {
                        if (in_array($cert['skill_id'], $skillIds)) {
                            $memberSkillCount++;
                        }
                    }
                    $skillCount = $skillSet->getSkillCount();
                    if ($memberSkillCount > 0 && $memberSkillCount < $skillCount) {
                        if (!isset($interests[$skillSet->getUid()])) {
                            $interests[$skillSet->getUid()] = 0;
                        }
                        $interests[$skillSet->getUid()]++;
                    }
                }

                arsort($interests);
            }

            $currentMonthVerifications = 0;
            $lastMonthVerifications = 0;
            $composition = [];
            $lastYearCerts = [];
            $sumVerifications = 0;
            foreach ($certs as $cert) {
                $totalScore += self::getSkillPoints($cert);
                $sumVerifications++;
                $date = new DateTime($cert['grant_date']);
                $diff = $now->diff($date);

                if ($diff->days <= 30) {
                    $currentMonthVerifications++;
                } elseif ($diff->days <= 60) {
                    $lastMonthVerifications++;
                }
                $numDaysToCompare = 365;
                // Exclude days until end of current month of last year
                if ($diff->days <= 365
                    && $now->format('m') == $date->format('m')
                    && $now->format('d') <= $date->format('d')) {
                    $numDaysToCompare -= $now->format('t') - $now->format('d');
                }

                if ($diff->days <= $numDaysToCompare) {
                    $lastYearCerts[] = $cert;
                }

                /** @var ?Skill $skill */
                $skill = $this->skillRepository->findByUid($cert['skill_id']);
                if ($skill) {
                    foreach ($skill->getBrands() as $skillBrand) {
                        $level = $cert['tier1'] ? 1 : ($cert['tier2'] ? 2 : ($cert['tier3'] ? 3 : ($cert['tier4'] ? 4 : 0)));
                        if ($level) {
                            if (!isset($composition[$skillBrand->getUid()]['tier' . $level])) {
                                $composition[$skillBrand->getUid()]['tier' . $level] = 0;
                            }
                            $composition[$skillBrand->getUid()]['tier' . $level]++;
                        }
                    }
                }
            }

            $currentMonthCertifiers = 0;
            $lastMonthCertifiers = 0;
            $sumIssued = 0;
            $certifierList = [];
            // Get Verifications for Brand Certifiers
            // TODO only correct if certifier is never deleted from brand
            $certifiers = $this->certifierRepository->findByBrandId($brandId);
            /** @var Certifier $certifier */
            foreach ($certifiers as $certifier) {
                $certifierList[] = $certifier->getUid();
                $certifierCerts = $this->certificationRepository->findAcceptedByCertifier($certifier);
                foreach ($certifierCerts as $cert) {
                    $sumIssued++;
                    $diff = $now->diff($cert->getGrantDate());
                    if ($diff->days <= 30) {
                        $currentMonthCertifiers++;
                    } elseif ($diff->days <= 60) {
                        $lastMonthCertifiers++;
                    }
                }
            }

            $qbStatistics->insert('tx_skills_domain_model_organisationstatistics')
                ->values([
                    'brand' => $brandId,
                    'total_score' => $totalScore,
                    'current_month_users' => $currentMonthUsers,
                    'last_month_users' => $lastMonthUsers,
                    'current_month_verifications' => $currentMonthVerifications,
                    'last_month_verifications' => $lastMonthVerifications,
                    'current_month_issued' => $currentMonthCertifiers,
                    'sum_verifications' => $sumVerifications,
                    'sum_supported_skills' => $this->getSupportedCount($certifierList),
                    'sum_skills' => $this->skillRepository->findByBrand($brandId)->count(),
                    'expertise' => json_encode($this->getExpertiseForBrand($brandId)),
                    'last_month_issued' => $lastMonthCertifiers,
                    'sum_issued' => $sumIssued,
                    'monthly_scores' => json_encode($this->calculateMonthlyVerificationStats($lastYearCerts, $now)),
                    'interests' => json_encode($interests),
                    'potential' => json_encode($potential),
                    'composition' => json_encode($composition),
                ])->executeStatement();
        }
    }

    public function calculateUserActivityStatistics(): void
    {
        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'full');
        $users = $this->userRepository->findAll();

        $statementVerifications = $this->initCertificationsStatement();

        /** @var User $user */
        foreach ($users as $user) {
            $monthlyScores = array_fill(1, 12, 0);
            $statementVerifications->bindValue(1, $user->getUid());
            $result = $statementVerifications->executeQuery();
            $certs = $result->fetchAllAssociative();

            foreach ($certs as $cert) {
                $date = new DateTime($cert['grant_date']);
                $diff = $now->diff($date);
                if ($diff->days <= 365) {
                    $date = new DateTime($cert['grant_date']);
                    $month = $date->format('n');
                    $monthlyScores[$month] += self::getSkillPoints($cert);
                }
            }

            for ($i = 1; $i <= $now->format('n'); $i++) {
                $keys = array_keys($monthlyScores);
                $val = $monthlyScores[$keys[0]];
                unset($monthlyScores[$keys[0]]);
                $monthlyScores[$keys[0]] = $val;
            }
            $user->setMonthlyActivity(json_encode($monthlyScores));
            $this->userRepository->update($user);
        }
        $this->persistenceManager->persistAll();
    }

    private function getSupportedCount(array $certifierList): int
    {
        if ($certifierList === []) {
            return 0;
        }

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_skills_domain_model_certifierpermission');
        $qb->select('p.skill')
            ->from('tx_skills_domain_model_certifierpermission', 'p')
            ->join('p', 'tx_skills_domain_model_skill', 's', 's.uid = p.skill')
            ->where(
                $qb->expr()->in('p.certifier', $certifierList)
            )
            ->groupBy('p.skill');

        return count($qb->executeQuery()->fetchAllAssociative());
    }

    private function initCertificationsStatement(): Statement
    {
        $qbCertifications = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_skills_domain_model_certification');
        return $qbCertifications
            ->select('c.uid as uid', 'c.brand', 'c.skill as skill_id', 'c.tier1', 'c.tier2', 'c.tier3', 'c.tier4', 'c.grant_date')
            ->from('tx_skills_domain_model_certification', 'c')
            ->join('c', 'tx_skills_domain_model_membershiphistory', 'h', 'c.uid = h.verification')
            ->where(
                $qbCertifications->expr()->eq('c.user', $qbCertifications->createPositionalParameter(1, Connection::PARAM_INT)),
                $qbCertifications->expr()->eq('h.brand', $qbCertifications->createPositionalParameter(2, Connection::PARAM_INT)),
                $qbCertifications->expr()->isNotNull('c.grant_date'),
                $qbCertifications->expr()->isNull('c.revoke_date')
            )
            ->orderBy('c.grant_date')
            ->prepare();
    }

    private function getAllUserIdsForBrand(int $brandId): array
    {
        $qbHistory = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_skills_domain_model_membershiphistory');
        return $qbHistory
            ->select('c.user')
            ->from('tx_skills_domain_model_membershiphistory', 'h')
            ->join('h', 'tx_skills_domain_model_certification', 'c', 'h.verification = c.uid')
            ->where(
                $qbHistory->expr()->eq('h.brand', $qbHistory->createNamedParameter($brandId, Connection::PARAM_INT)),
            )
            ->groupBy('c.user')
            ->executeQuery()->fetchAllAssociative();
    }

    /**
     * returns the number of skillpoints for all users of the given brand grouped by the brand of the skill
     */
    private function getExpertiseForBrand(int $brandUid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_skills_domain_model_certification');
        $queryBuilder = $queryBuilder
            ->select('c.uid as certification_uid', 'c.tier1 as tier1', 'c.tier3 as tier3', 'b.name', 'b.uid as brand_id')
            ->from('tx_skills_domain_model_certification', 'c')
            ->join('c', 'tx_skills_domain_model_skill', 's', 'c.skill = s.uid')
            ->join('s', 'tx_skills_skill_brand_mm', 'sb_mm', 's.uid = sb_mm.uid_local')
            ->join('sb_mm', 'tx_skills_domain_model_brand', 'b', 'sb_mm.uid_foreign = b.uid')
            ->join('c', 'tx_skills_user_organisation_mm', 'ub_mm', 'c.user = ub_mm.uid_local')
            ->join('ub_mm', 'tx_skills_domain_model_brand', 'ub', 'ub_mm.uid_foreign = ub.uid')
            ->where(
                $queryBuilder->expr()->eq('ub.uid', $queryBuilder->createNamedParameter($brandUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->isNotNull('c.grant_date'),
                $queryBuilder->expr()->isNull('c.revoke_date')
            )
            ->orderBy('c.uid')
            ->addOrderBy('sb_mm.sorting');

        $result = $queryBuilder->executeQuery();
        $mapping = [];
        $certificationsDone = [];
        while ($data = $result->fetchAssociative()) {
            if (isset($certificationsDone[$data['certification_uid']])) {
                continue;
            }
            $certificationsDone[$data['certification_uid']] = true;
            $points = self::getSkillPoints($data);
            if (isset($mapping[$data['brand_id']])) {
                $mapping[$data['brand_id']]['points'] += $points;
            } else {
                $mapping[$data['brand_id']] = ['points' => $points, 'label' => $data['name']];
            }
        }

        return array_values($mapping);
    }

    /**
     * Calculate monthly verification stats
     *
     * @param array $lastYearCerts
     * @param DateTimeImmutable $now
     * @return array
     * @throws Exception
     */
    private function calculateMonthlyVerificationStats(array $lastYearCerts, DateTimeImmutable $now): array
    {
        $monthly_scores = array_fill(1, 12, 0);
        foreach ($lastYearCerts as $cert) {
            $date = new DateTime($cert['grant_date']);
            $month = $date->format('n');
            $monthly_scores[$month] += 1;
        }

        for ($i = 1; $i <= $now->format('n'); $i++) {
            $keys = array_keys($monthly_scores);
            $val = $monthly_scores[$keys[0]];
            unset($monthly_scores[$keys[0]]);
            $monthly_scores[$keys[0]] = $val;
        }
        return $monthly_scores;
    }
}
