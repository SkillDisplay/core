<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 *
 ***/

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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\Query;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * The repository for Certifications
 */
class CertificationRepository extends BaseRepository
{
    /**
     * @param Skill[] $skills
     * @param User $user
     * @param bool $includePending
     * @return Certification[]|QueryResultInterface
     * @throws InvalidQueryException
     */
    public function findBySkillsAndUser(array $skills, User $user, bool $includePending = true)
    {
        $q = $this->getQuery();

        $constraints = [
            $q->in('skill', $skills),
            $q->equals('user', $user),
            $q->equals('revokeDate', null),
            $q->equals('denyDate', null),
            $q->logicalOr([
                $q->equals('expireDate', null),
                $q->greaterThan('expireDate', date('Y-m-d H:i:s', $GLOBALS['EXEC_TIME'])),
            ]),
        ];
        if (!$includePending) {
            $constraints[] = $q->logicalNot($q->equals('grantDate', null));
        }
        return $q->matching($q->logicalAnd($constraints))->execute();
    }

    /**
     * @param QueryResultInterface|Certification[] $certifications
     * @return array
     */
    protected function splitCertificationInGroups($certifications): array
    {
        $list = [];
        $group = null;
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
        return $this->splitCertificationInGroups($q->matching($q->logicalAnd(
            [
                $q->equals('user', $user),
                $q->equals('grantDate', null),
                $q->equals('denyDate', null),
                $q->equals('revokeDate', null),
            ]
        ))->execute());
    }

    public function findPendingByCertifier(Certifier $certifier): array
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups($q->matching($q->logicalAnd(
            [
                $q->equals('certifier', $certifier),
                $q->equals('grantDate', null),
                $q->equals('denyDate', null),
                $q->equals('revokeDate', null),
            ]
        ))->execute());
    }

    public function findCompletedByCertifier(Certifier $certifier): array
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups($q->matching($q->logicalAnd(
            [
                $q->equals('certifier', $certifier),
                $q->logicalOr([
                    $q->logicalNot($q->equals('grantDate', null)),
                    $q->logicalNot($q->equals('denyDate', null)),
                    $q->logicalNot($q->equals('revokeDate', null)),
                ]),
            ]
        ))->execute());
    }

    public function findAccepted(User $user): array
    {
        return $this->splitCertificationInGroups($this->findAcceptedForUser($user));
    }

    public function findAcceptedForUser(User $user): QueryResultInterface
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'grantDate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->matching($q->logicalAnd(
            [
                $q->equals('user', $user),
                $q->logicalNot($q->equals('grantDate', null)),
                $q->equals('revokeDate', null),
            ]
        ))->execute();
    }

    public function findDeclined(User $user): array
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'denyDate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups($q->matching($q->logicalAnd(
            [
                $q->equals('user', $user),
                $q->logicalNot($q->equals('denyDate', null)),
            ]
        ))->execute());
    }

    public function findRevoked(User $user): array
    {
        $q = $this->getQuery();
        $q->setOrderings([
            'revokeDate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups($q->matching($q->logicalAnd(
            [
                $q->equals('user', $user),
                $q->logicalNot($q->equals('revokeDate', null)),
            ]
        ))->execute());
    }

    public function addTier(
        Skill $skill,
        User $user,
        int $tier,
        string $comment,
        Certifier $certifier = null,
        Campaign $campaign = null,
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
                $verification->setVerifierName("{$certifier->getUser()->getFirstName()} {$certifier->getUser()->getLastName()}");
            } else {
                $verification->setVerifierName($certifier->getTestSystem());
            }
        }
        $verification->setBrand($certifier ? $certifier->getBrand() : null);
        $verification->setBrandName($certifier ? $certifier->getBrand()->getName() : "");
        $verification->setRequestGroup($group);
        $parent = $verification->getRequestGroupParent();
        $verification->setGroupName($parent ? $parent->getName() : "");

        if ($campaign) {
            $verification->setCampaign($campaign);
        }
        $verification->setComment($comment);
        // dirty workaround as extbase fails to handle crdate correctly
        $verification->setCrdate($GLOBALS['EXEC_TIME']);

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

    public function findByPrerequisiteAndUser(RewardPrerequisite $prerequisite, User $user): QueryResultInterface
    {
        $q = $this->createQuery();

        $constraints = [
            $q->equals('user', $user),
            $q->equals('skill', $prerequisite->getSkill()),
            $q->equals('tier' . $prerequisite->getLevel(), 1),
            $q->logicalNot($q->equals('grantDate', null)),
            $q->equals('revokeDate', null),
            $q->logicalOr([
                $q->equals('expireDate', null),
                $q->greaterThan('expireDate', date('Y-m-d H:i:s', $GLOBALS['EXEC_TIME'])),
            ]),
        ];
        if ($prerequisite->getBrand()) {
            $constraints[] = $q->equals('brand', $prerequisite->getBrand());
        }
        $q->matching($q->logicalAnd($constraints));

        return $q->execute();
    }

    public function findByGrantDateAndBrandsAndSkillSets(
        ?DateTime $from,
        ?DateTime $to,
        array $brands = [],
        array $skillSets = [],
        ?User $user = null,
        int $level = 0
    ): QueryResultInterface {
        /** @var Query $q */
        $q = $this->createQuery();
        $constraints = [
            $q->equals('revokeDate', null),
            $q->equals('denyDate', null),
            $q->logicalOr([
                $q->equals('expireDate', null),
                $q->lessThanOrEqual('expireDate', date('Y-m-d H:i:s', $GLOBALS['EXEC_TIME'])),
            ]),
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
            $constraints[] = $q->in('brand', $brands);
        }
        if ($skillSets) {
            $skillSetRepo = $this->objectManager->get(SkillPathRepository::class);
            $skills = [];
            foreach ($skillSets as $setId) {
                if ($setId instanceof SkillPath) {
                    $set = $setId;
                } else {
                    $set = $skillSetRepo->findByUid((int)$setId);
                }
                $setSkillsIds = array_map(function (Skill $skill) {
                    return $skill->getUid();
                }, $set->getSkills()->toArray());
                foreach ($setSkillsIds as $skillId) {
                    if (!in_array($skillId, $skills)) {
                        $skills[] = $skillId;
                    }
                }
            }
            $constraints[] = $q->in('skill', $skills);
        }
        if ($user) {
            $constraints[] = $q->equals('user', $user);
        }
        if ($level) {
            $constraints[] = $q->equals('tier' . $level, 1);
        }
        $q->matching($q->logicalAnd($constraints));
        return $q->execute();
    }

    public function findCompletedByOrganisationMembership(
        int $brandId,
        DateTime $from,
        DateTime $to
    ): QueryResultInterface {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false);
        $q->matching($q->logicalAnd([
            //$q->contains('user.organisations', $brandId),
            $q->greaterThanOrEqual('grantDate', $from->format('Y-m-d H:i:s')),
            $q->lessThanOrEqual('grantDate', $to->format('Y-m-d H:i:s')),
            $q->equals('revokeDate', null),
            $q->equals('denyDate', null),
            $q->logicalOr([
                $q->equals('expireDate', null),
                $q->lessThanOrEqual('expireDate', date('Y-m-d H:i:s', $GLOBALS['EXEC_TIME'])),
            ]),
        ]));
        return $q->execute();
    }

    public function findPendingByCertifierUser(User $user, int $limit = 0): array
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false);
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        $groups = $this->splitCertificationInGroups($q->matching($q->logicalAnd(
            [
                $q->equals('certifier.user', $user),
                $q->equals('grantDate', null),
                $q->equals('denyDate', null),
                $q->equals('revokeDate', null),
            ]
        ))->execute());
        if ($limit) {
            array_splice($groups, $limit);
        }
        return $groups;
    }

    public function findByCertifier(Certifier $certifier): array
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false);
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups($q->matching($q->logicalAnd(
            [
                $q->equals('certifier', $certifier),
            ]
        ))->execute());
    }

    public function findByUser(User $user): array
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false);
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $this->splitCertificationInGroups($q->matching($q->logicalAnd(
            [
                $q->equals('user', $user),
            ]
        ))->execute());
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

        $skillSets = $this->objectManager->get(SkillPathRepository::class)->findBySearchWord($searchWord, []);
        $likeParts = array_map(function (SkillPath $s) {
            return 'skillpath-' . $s->getUid() . '%';
        }, $skillSets);

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

            $subConstraints[] = $q->logicalOr($subConditions);
        }
        if ($requestGroupLike) {
            $subConstraints[] = $q->logicalOr($requestGroupLike);
        }
        $constraints[] = $q->logicalOr($subConstraints);
        if (!empty($constraints)) {
            $q->matching($q->logicalAnd($constraints));
        }

        return $this->splitCertificationInGroups($q->execute());
    }

    public function findAcceptedByCertifier(Certifier $verifier): array
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false);
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $q->matching($q->logicalAnd(
            [
                $q->equals('certifier', $verifier),
                $q->logicalNot($q->equals('grant_date', null)),
            ]
        ))->execute()->toArray();
    }

    public function findAcceptedOrDeniedByUser(?User $user, ?Certifier $verifier): array
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false);
        $q->setOrderings([
            'crdate' => QueryInterface::ORDER_DESCENDING,
            'requestGroup' => QueryInterface::ORDER_ASCENDING,
        ]);
        $constraints = [
            $q->logicalOr([
                $q->logicalNot($q->equals('grant_date', null)),
                $q->logicalNot($q->equals('deny_date', null)),
            ]),
        ];
        if ($user) {
            $constraints[] = $q->equals('user', $user);
        }
        if ($verifier) {
            $constraints[] = $q->equals('certifier', $verifier);
        }
        return $q->matching($q->logicalAnd($constraints))->execute()->toArray();
    }

    /**
     * @param Brand $organisation
     * @return Certification[]
     */
    public function findUnbalanced(Brand $organisation): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_skills_domain_model_certification');
        $rows = $qb->select('c.*')
                     ->from('tx_skills_domain_model_certification', 'c')
                     ->leftJoin('c', 'tx_skills_domain_model_verificationcreditusage', 'u', 'u.verification = c.uid')
                     ->where(
                         $qb->expr()->eq('c.brand', $qb->createNamedParameter($organisation->getUid(), Connection::PARAM_INT)),
                         $qb->expr()->neq('c.price', $qb->createNamedParameter(0, Connection::PARAM_INT)),
                         $qb->expr()->isNotNull('c.grant_date'),
                         $qb->expr()->isNull('u.uid')
                     )
                     ->execute()->fetchAll();
        if ($rows) {
            $dataMapper = $this->objectManager->get(DataMapper::class);
            return $dataMapper->map(Certification::class, $rows);
        }
        return [];
    }

    public function findAcceptedByOrganisation(Brand $organisation, DateTime $from, DateTime $to): array
    {
        $q = $this->createQuery();
        $q->getQuerySettings()->setRespectStoragePage(false);
        $q->matching($q->logicalAnd([
            $q->equals('brand', $organisation),
            $q->greaterThanOrEqual('grantDate', $from->format('Y-m-d H:i:s')),
            $q->lessThanOrEqual('grantDate', $to->format('Y-m-d H:i:s')),
            $q->equals('revokeDate', null),
            $q->equals('denyDate', null),
            $q->logicalOr([
                $q->equals('expireDate', null),
                $q->lessThanOrEqual('expireDate', date('Y-m-d H:i:s', $GLOBALS['EXEC_TIME'])),
            ]),
        ]));
        return $this->splitCertificationInGroups($q->execute());
    }
}
