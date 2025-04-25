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

use DateTime;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Campaign;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\RewardPrerequisite;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for Certifications
 * @extends BaseRepository<Certification>
 */
class CertificationRepository extends BaseRepository
{
    /**
     * @param Skill[] $skills
     */
    public function findBySkillsAndUser(array $skills, User $user, bool $includePending = true): QueryResultInterface
    {
        $q = $this->getQuery();

        // this is a workaround for Extbase which uses the localizedUid of the skill
        // see Typo3DbQueryParser::createTypedNamedParameter
        $skillIds = array_map(fn(Skill $s) => $s->getUid(), $skills);

        $constraints = [
            $q->in('skill', $skillIds),
            $q->equals('user', $user),
            $q->equals('revokeDate', null),
            $q->equals('denyDate', null),
            $q->logicalOr(
                $q->equals('expireDate', null),
                $q->greaterThan('expireDate', $this->getCurrentTimeForConstraint())
            ),
        ];
        if (!$includePending) {
            $constraints[] = $q->logicalNot($q->equals('grantDate', null));
        }
        return $q->matching($q->logicalAnd(...$constraints))->execute();
    }

    /**
     * @param QueryResultInterface<int, Certification> $certifications
     */
    protected function splitCertificationInGroups(QueryResultInterface $certifications): array
    {
        $list = [];
        $group = null;
        /** @var Certification $cert */
        foreach ($certifications as $cert) {
            if (!$group || !$group['id'] || $group['id'] !== $cert->getRequestGroup()) {
                if ($group) {
                    $list[] = $group;
                }
                $group = [
                    'id' => $cert->getRequestGroup(),
                    'certs' => [$cert],
                ];
            } else {
                $group['certs'][] = $cert;
            }
        }
        if ($group) {
            $list[] = $group;
        }
        return $list;
    }

    public function findPending(User $user): array
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups(
            $q->matching(
                $q->logicalAnd(
                    $q->equals('user', $user),
                    $q->equals('grantDate', null),
                    $q->equals('denyDate', null),
                    $q->equals('revokeDate', null),
                )
            )->execute()
        );
    }

    public function findPendingByCertifier(Certifier $certifier): array
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups(
            $q->matching(
                $q->logicalAnd(
                    $q->equals('certifier', $certifier),
                    $q->equals('grantDate', null),
                    $q->equals('denyDate', null),
                    $q->equals('revokeDate', null),
                )
            )->execute()
        );
    }

    public function findCompletedByCertifier(Certifier $certifier): array
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups(
            $q->matching(
                $q->logicalAnd(
                    $q->equals('certifier', $certifier),
                    $q->logicalOr(
                        $q->logicalNot($q->equals('grantDate', null)),
                        $q->logicalNot($q->equals('denyDate', null)),
                        $q->logicalNot($q->equals('revokeDate', null))
                    ),
                )
            )->execute()
        );
    }

    public function findAccepted(User $user): array
    {
        return $this->splitCertificationInGroups($this->findAcceptedForUser($user));
    }

    /**
     * @return QueryResultInterface<int, Certification>
     */
    public function findAcceptedForUser(User $user): QueryResultInterface
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'grantDate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->matching(
            $q->logicalAnd(
                $q->equals('user', $user),
                $q->logicalNot($q->equals('grantDate', null)),
                $q->equals('revokeDate', null),
            )
        )->execute();
    }

    public function findDeclined(User $user): array
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'denyDate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups(
            $q->matching(
                $q->logicalAnd(
                    $q->equals('user', $user),
                    $q->logicalNot($q->equals('denyDate', null)),
                )
            )->execute()
        );
    }

    public function findRevoked(User $user): array
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'revokeDate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups(
            $q->matching(
                $q->logicalAnd(
                    $q->equals('user', $user),
                    $q->logicalNot($q->equals('revokeDate', null)),
                )
            )->execute()
        );
    }

    /**
     * @param Skill $skill
     * @param User $user
     * @param int $tier
     * @param string $comment
     * @param Certifier|null $certifier
     * @param Campaign|null $campaign
     * @param string $group
     * @return Certification
     */
    public function addTier(
        Skill $skill,
        User $user,
        int $tier,
        string $comment,
        ?Certifier $certifier = null,
        ?Campaign $campaign = null,
        string $group = ''
    ): Certification {
        /** @var Certification $verification */
        $verification = GeneralUtility::makeInstance(Certification::class);
        $verification->setUser($user);
        $verification->setUserUsername($user->getUsername());
        $verification->setUserFirstname($user->getFirstName());
        $verification->setUserLastname($user->getLastName());
        $verification->setSkill($skill);
        $verification->setSkillTitle($skill->getTitle());
        $verification->setCertifier($certifier);
        $verification->setRewardable($certifier && $certifier->getBrand()->getBillable());
        if ($certifier) {
            if ($certifier->getUser()) {
                $verification->setVerifierName(
                    "{$certifier->getUser()->getFirstName()} {$certifier->getUser()->getLastName()}"
                );
            } else {
                $verification->setVerifierName($certifier->getTestSystem());
            }
        }
        $verification->setBrand($certifier?->getBrand());
        $verification->setBrandName($certifier ? $certifier->getBrand()->getName() : '');
        $verification->setRequestGroup($group);
        $parent = $verification->getRequestGroupParent();
        $verification->setGroupName($parent ? $parent->getName() : '');

        if ($campaign) {
            $verification->setCampaign($campaign);
        }
        $verification->setComment($comment);
        // dirty workaround as extbase fails to handle crdate correctly
        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $verification->setCrdate($now);

        switch ($tier) {
            case 4:
                $verification->setTier4(true);
                break;
            case 3:
                $verification->setTier3(true);
                break;
            case 2:
                $verification->setTier2(true);
                break;
            case 1:
                $verification->setTier1(true);
                break;
        }

        $this->add($verification);
        return $verification;
    }

    /**
     * @param RewardPrerequisite $prerequisite
     * @param User $user
     * @return QueryResultInterface
     */
    public function findByPrerequisiteAndUser(RewardPrerequisite $prerequisite, User $user): QueryResultInterface
    {
        $q = $this->createQuery();

        $constraints = [
            $q->equals('user', $user),
            $q->equals('skill', $prerequisite->getSkill()->getUid()),
            $q->equals('tier' . $prerequisite->getLevel(), 1),
            $q->logicalNot($q->equals('grantDate', null)),
            $q->equals('revokeDate', null),
            $q->logicalOr(
                $q->equals('expireDate', null),
                $q->greaterThan('expireDate', $this->getCurrentTimeForConstraint())
            ),
        ];
        if ($prerequisite->getBrand()) {
            $constraints[] = $q->equals('brand', $prerequisite->getBrand()->getUid());
        }
        $q->matching($q->logicalAnd(...$constraints));

        return $q->execute();
    }

    /**
     * @param DateTime|null $from
     * @param DateTime|null $to
     * @param Brand[]|int[] $brands
     * @param SkillPath[]|int[] $skillSets
     * @param int $userId
     * @param int $level
     * @return QueryResultInterface
     */
    public function findByGrantDateAndBrandsAndSkillSets(
        ?DateTime $from,
        ?DateTime $to,
        array $brands = [],
        array $skillSets = [],
        int $userId = 0,
        int $level = 0
    ): QueryResultInterface {
        /** @var Query $q */
        $q = $this->createQuery();
        $constraints = [
            $q->equals('revokeDate', null),
            $q->equals('denyDate', null),
            $q->logicalOr(
                $q->equals('expireDate', null),
                $q->lessThanOrEqual('expireDate', $this->getCurrentTimeForConstraint())
            ),
        ];
        if ($from) {
            $constraints[] = $q->greaterThanOrEqual('grantDate', $from->format('Y-m-d H:i:s'));
        }
        if ($to) {
            $constraints[] = $q->lessThanOrEqual('grantDate', $to->format('Y-m-d H:i:s'));
        }
        if (!$from && !$to) {
            $constraints[] = $q->logicalNot($q->equals('grantDate', null));
        }
        if ($brands) {
            $brandIds = array_map(function ($s) {
                if ($s instanceof Brand) {
                    return $s->getUid();
                }
                return (int)$s;
            }, $brands);
            $constraints[] = $q->in('brand', $brandIds);
        }
        if ($skillSets) {
            /** @var SkillPathRepository $skillSetRepo */
            $skillSetRepo = GeneralUtility::makeInstance(SkillPathRepository::class);
            $skillIds = [];
            foreach ($skillSets as $setId) {
                if ($setId instanceof SkillPath) {
                    $set = $setId;
                } else {
                    /** @var SkillPath $set */
                    $set = $skillSetRepo->findByUid((int)$setId);
                }
                $setSkillsIds = array_map(fn(Skill $skill) => $skill->getUid(), $set->getSkills()->toArray());
                $skillIds = array_merge($skillIds, $setSkillsIds);
            }
            $skillIds = array_unique($skillIds, SORT_NUMERIC);
            $constraints[] = $q->in('skill', $skillIds);
        }
        if ($userId) {
            $constraints[] = $q->equals('user.uid', $userId);
        }
        if ($level) {
            $constraints[] = $q->equals('tier' . $level, 1);
        }
        $q->matching($q->logicalAnd(...$constraints));
        return $q->execute();
    }

    public function findPendingByCertifierUser(User $user, int $limit = 0): array
    {
        $q = $this->createQuery();
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        $groups = $this->splitCertificationInGroups(
            $q->matching(
                $q->logicalAnd(
                    $q->equals('certifier.user', $user),
                    $q->equals('grantDate', null),
                    $q->equals('denyDate', null),
                    $q->equals('revokeDate', null),
                )
            )->execute()
        );
        if ($limit) {
            array_splice($groups, $limit);
        }
        return $groups;
    }

    public function findByCertifier(Certifier $certifier): array
    {
        $q = $this->createQuery();
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups(
            $q->matching(
                $q->equals('certifier', $certifier)
            )->execute()
        );
    }

    public function findByUser(User $user): array
    {
        $q = $this->createQuery();
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups($q->matching($q->equals('user', $user))->execute());
    }

    public function findBySearchWord(string $searchWord, User $user): array
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'grantDate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        $constraints = [
            $q->equals('user', $user),
        ];

        /** @var SkillPathRepository $skillSetRepo */
        $skillSetRepo = GeneralUtility::makeInstance(SkillPathRepository::class);
        $skillSets = $skillSetRepo->findBySearchWord($searchWord, []);
        $likeParts = array_map(fn(SkillPath $s) => 'skillpath-' . $s->getUid() . '%', $skillSets);

        $requestGroupLike = [];
        foreach ($likeParts as $likePart) {
            $requestGroupLike[] = $q->like('requestGroup', $likePart);
        }

        $subConstraints = [];
        $searchWords = str_getcsv($searchWord, ' ');
        $searchWords = array_filter($searchWords);
        foreach ($searchWords as $searchWord) {
            // escape SQL like special chars
            $searchWordLike = addcslashes($searchWord, '_%');
            $subConditions = [
                $q->like('brand.name', '%' . $searchWordLike . '%'),
                $q->like('skill.title', '%' . $searchWordLike . '%'),
                $q->like('grantDate', '%' . $searchWordLike . '%'),
                $q->like('certifier.brand.name', '%' . $searchWordLike . '%'),
                $q->like('certifier.user.firstName', '%' . $searchWordLike . '%'),
                $q->like('certifier.user.lastName', '%' . $searchWordLike . '%'),
            ];

            $subConstraints[] = $q->logicalOr(...$subConditions);
        }
        if ($requestGroupLike) {
            $subConstraints[] = $q->logicalOr(...$requestGroupLike);
        }
        if ($subConstraints) {
            $constraints[] = $q->logicalOr(...$subConstraints);
        }
        if (count($constraints) > 1) {
            $q->matching($q->logicalAnd(...$constraints));
        } else {
            $q->matching($constraints[0]);
        }

        return $this->splitCertificationInGroups($q->execute());
    }

    /**
     * @return Certification[]
     */
    public function findAcceptedByCertifier(Certifier $verifier): array
    {
        $q = $this->createQuery();
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        /** @var Certification[] $res */
        $res = $q->matching(
            $q->logicalAnd(
                $q->equals('certifier', $verifier),
                $q->logicalNot($q->equals('grant_date', null)),
            )
        )->execute()->toArray();
        return $res;
    }

    public function findAcceptedOrDeniedByUser(?User $user, ?Certifier $verifier): array
    {
        $q = $this->createQuery();
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        $constraints = [
            $q->logicalNot($q->equals('grantDate', null)),
            $q->equals('denyDate', null),
            $q->equals('revokeDate', null),
        ];
        if ($user) {
            $constraints[] = $q->equals('user', $user);
        }
        if ($verifier) {
            $constraints[] = $q->equals('certifier', $verifier);
        }
        return $q->matching($q->logicalAnd(...$constraints))->execute()->toArray();
    }

    /**
     * @phpstan-return list<Certification>
     */
    public function findUnbalanced(Brand $organisation): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'tx_skills_domain_model_certification'
        );
        $rows = $qb->select('c.*')
            ->from('tx_skills_domain_model_certification', 'c')
            ->leftJoin('c', 'tx_skills_domain_model_verificationcreditusage', 'u', 'u.verification = c.uid')
            ->where(
                $qb->expr()->eq('c.brand', $qb->createNamedParameter($organisation->getUid(), Connection::PARAM_INT)),
                $qb->expr()->neq('c.price', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                $qb->expr()->isNotNull('c.grant_date'),
                $qb->expr()->isNull('u.uid')
            )
            ->executeQuery()->fetchAllAssociative();
        return $this->mapRows($rows);
    }

    public function findAcceptedByOrganisation(Brand $organisation, DateTime $from, DateTime $to): array
    {

        $q = $this->createQuery();
        $q->matching(
            $q->logicalAnd(
                $q->equals('brand', $organisation->getUid()),
                $q->greaterThanOrEqual('grantDate', $from->format('Y-m-d H:i:s')),
                $q->lessThanOrEqual('grantDate', $to->format('Y-m-d H:i:s')),
                $q->equals('revokeDate', null),
                $q->equals('denyDate', null),
                $q->logicalOr(
                    $q->equals('expireDate', null),
                    $q->lessThanOrEqual('expireDate', $this->getCurrentTimeForConstraint())
                ),
            )
        );
        return $this->splitCertificationInGroups($q->execute());
    }

    public function findByRequestGroup(string $group): array|QueryResultInterface
    {
        $q = $this->createQuery();
        $q->matching($q->equals('requestGroup', $group));
        return $q->execute();
    }

    public function countByBrand(Brand $brand): int
    {
        $query = $this->createQuery();
        return $query->matching($query->equals('brand', $brand))->execute()->count();
    }

    public function findBySkill(Skill $skill): QueryResultInterface
    {
        $query = $this->createQuery();
        return $query->matching($query->equals('skill', $skill))->execute();
    }

    protected function getCurrentTimeForConstraint(): string
    {
        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        return date('Y-m-d H:i:s', $now);
    }
}
