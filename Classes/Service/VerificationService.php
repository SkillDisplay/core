<?php

declare(strict_types=1);
/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 **/

namespace SkillDisplay\Skills\Service;

use DateTime;
use LogicException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Campaign;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\MembershipHistory;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Model\VerificationCreditUsage;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\MembershipHistoryRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditPackRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditUsageRepository;
use SkillDisplay\Skills\Event\VerificationAddedEvent;
use SkillDisplay\Skills\Event\VerificationUpdatedEvent;
use SkillDisplay\Skills\SkillUpHookInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class VerificationService implements SingletonInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const SKILL_ACTION_NONE = 0;
    public const SKILL_ACTION_DELETE = 1;

    protected CertificationRepository $certificationRepository;
    protected CertifierRepository $certifierRepository;
    protected VerificationCreditPackRepository $creditPackRepository;
    protected VerificationCreditUsageRepository $creditUsageRepository;
    protected MembershipHistoryRepository $membershipHistoryRepository;

    protected PersistenceManager $persistenceManager;

    /** @var SkillUpHookInterface[] */
    protected array $hooks = [];

    protected bool $disableCertoBot = false;

    protected array $creditSettings = [];

    public function __construct(
        CertificationRepository $certificationRepository,
        PersistenceManager $persistenceManager,
        CertifierRepository $certifierRepository,
        VerificationCreditPackRepository $creditPackRepository,
        VerificationCreditUsageRepository $creditUsageRepository,
        MembershipHistoryRepository $membershipHistoryRepository
    ) {
        $this->certificationRepository = $certificationRepository;
        $this->persistenceManager = $persistenceManager;
        $this->certifierRepository = $certifierRepository;
        $this->creditPackRepository = $creditPackRepository;
        $this->creditUsageRepository = $creditUsageRepository;
        $this->membershipHistoryRepository = $membershipHistoryRepository;
    }

    /**
     * Register SkillUp ad message hook
     *
     * @param SkillUpHookInterface $hook
     */
    public function registerHook(SkillUpHookInterface $hook): void
    {
        $this->hooks[] = $hook;
    }

    /**
     * Run hooks for given SkillUp action
     *
     * @param string $action
     * @param Skill $skill
     * @param User $user
     * @param array $settings
     * @return string
     */
    public function triggerHooks(string $action, Skill $skill, User $user, array $settings): string
    {
        $content = '';
        foreach ($this->hooks as $hook) {
            foreach ($hook->getApplicableSkillActions() as $subscription) {
                if ($subscription['action'] === $action
                    && ($subscription['skill'] === '*' || $subscription['skill'] === $skill->getUid())
                ) {
                    $content .= $hook->getMessage($skill, $user, $settings);
                }
            }
        }
        return $content;
    }

    public function handleSkillUpRequest(
        array $skills,
        string $groupId,
        User $user,
        int $tier,
        string $comment,
        ?Certifier $certifier,
        ?Campaign $campaign,
        bool $autoConfirm
    ): array {
        $skippedSkills = [];
        $failedSkills = [];
        $result = [
            'verifications' => [],
            'failedSkills' => [],
            'errorMessage' => '',
        ];
        $validatedSkills = $this->validateSkillUp($skills, $user, $tier, $certifier);
        if ($groupId) {
            // skipping is only allowed for group-skillups
            foreach ($validatedSkills as $uid => $data) {
                if ($data['skip']) {
                    $skippedSkills[$uid] = $data;
                } else {
                    $failedSkills[$uid] = $data;
                }
            }
        } else {
            $failedSkills = $validatedSkills;
        }
        if (!empty($failedSkills)) {
            $result['failedSkills'] = $failedSkills;
            $result['errorMessage'] = 'Some skills are not verifiable.';
            return $result;
        }
        $skillsToProcess = [];
        foreach ($skills as $skill) {
            if (!isset($skippedSkills[$skill->getUid()]) ||
                $skippedSkills[$skill->getUid()]['action'] === VerificationService::SKILL_ACTION_DELETE
            ) {
                $skillsToProcess[] = $skill;
            }
        }
        // delete existing pending skillups for single skills (marked as action=deleted by the VerificationService)
        foreach ($skippedSkills as $skipped) {
            if ($skipped['action'] === VerificationService::SKILL_ACTION_DELETE) {
                $this->cancelSkillupRequest($skipped['conflictCert']);
            }
        }
        if ($autoConfirm && $tier !== Skill::LevelTierMap['self']) {
            $pointsNeeded = count($skillsToProcess) * (int)$this->creditSettings['tier' . $tier];
            $organisation = $certifier->getBrand();
            if (!$this->organisationHasEnoughCredit($organisation->getUid(), $pointsNeeded) && !$organisation->getCreditOverdraw()) {
                $result['errorMessage'] = 'Verification credit points are insufficient for the given skills.';
                return $result;
            }
        }
        $verifications = $this->processSkillUp($skillsToProcess, $tier, $user, $comment, $certifier, $campaign, $groupId);
        if ($autoConfirm) {
            foreach ($verifications as $verification) {
                $this->confirmSkillUp($verification, true);
            }
        }
        $redirectUrl = '';
        if ($certifier && $certifier->getUser() === null) {
            /** @var TestSystemProviderService $providerService */
            $providerService = GeneralUtility::makeInstance(TestSystemProviderService::class);
            $redirectUrl = $providerService->getProviderById($certifier->getTestSystem())->process($verifications);
        }
        $result['verifications'] = $verifications;
        $result['redirectUrl'] = $redirectUrl;
        return $result;
    }

    /**
     * @param Skill[] $skills
     * @param User $user
     * @param int $tier
     * @param Certifier|null $certifier
     * @return array
     * @throws InvalidQueryException
     */
    private function validateSkillUp(array $skills, User $user, int $tier, ?Certifier $certifier): array
    {
        $failedSkills = [];
        foreach ($skills as $subSkill) {
            if ($subSkill->isSkillable() && UserOrganisationsService::isSkillVisibleForUser($subSkill, $user)) {
                $certs = $this->certificationRepository->findBySkillsAndUser([$subSkill], $user);
                /** @var Certification $cert */
                foreach ($certs as $cert) {
                    if ($tier === 3 && $cert->isTier3()) {
                        $failedSkills[$subSkill->getUid()] = [
                            'skill' => $subSkill,
                            'conflictCert' => $cert,
                            'skip' => true,
                            'action' => self::SKILL_ACTION_NONE,
                            'reason' => 'Self-SkillUp only possible once.',
                        ];
                        break;
                    }
                    if ($cert->getLevelNumber() === $tier && $cert->isPending()) {
                        if ($cert->getRequestGroup()) {
                            // the certification is part of a group we have to fail hard
                            $failedSkills[$subSkill->getUid()] = [
                                'skill' => $subSkill,
                                'conflictCert' => $cert,
                                'skip' => false,
                                'action' => self::SKILL_ACTION_NONE,
                                'reason' => 'A grouped skillUp is currently pending. Cannot request a skillUp twice.',
                            ];
                            break;
                        }
                        // we can cancel the conflicting single request
                        $failedSkills[$subSkill->getUid()] = [
                            'skill' => $subSkill,
                            'conflictCert' => $cert,
                            'skip' => true,
                            'action' => self::SKILL_ACTION_DELETE,
                            'reason' => 'The pending request will be cancelled.',
                        ];

                    }
                    if ($certifier && $cert->getCertifier()
                        && $cert->getLevelNumber() === $tier
                        && $cert->getCertifier()->getUid() === $certifier->getUid()
                    ) {
                        // we can't request a skillUp twice from the same certifier
                        $failedSkills[$subSkill->getUid()] = [
                            'skill' => $subSkill,
                            'conflictCert' => $cert,
                            'skip' => true,
                            'action' => self::SKILL_ACTION_NONE,
                            'reason' => 'A skillUp can only be requested once per level and verifier.',
                        ];
                        break;
                    }
                }
            } else {
                $failedSkills[$subSkill->getUid()] = [
                    'skill' => $subSkill,
                    'conflictCert' => null,
                    'skip' => false,
                    'action' => self::SKILL_ACTION_NONE,
                    'reason' => 'This skill is dormant.',
                ];
            }
        }

        return $failedSkills;
    }

    private function processSkillUp(
        array $skills,
        int $tier,
        User $user,
        string $comment,
        ?Certifier $certifier,
        ?Campaign $campaign,
        string $groupId
    ): array {
        if (empty($skills)) {
            return [];
        }
        $tags = [];
        $certifications = [];
        foreach ($skills as $skill) {
            $certifications[] = $this->certificationRepository->addTier(
                $skill,
                $user,
                $tier,
                $comment,
                $certifier,
                $campaign,
                $groupId
            );
            $tags[] = $skill->getCacheTag($user->getUid());
        }
        foreach ($certifications as $verification) {
            $brands = $verification->getUser()->getOrganisations();
            /** @var Brand $brand */
            foreach ($brands as $brand) {
                $membership = new MembershipHistory();
                $membership->setBrand($brand);
                $membership->setBrandName($brand->getName());
                $membership->setVerification($verification);
                $this->membershipHistoryRepository->add($membership);
            }
        }
        $this->persistenceManager->persistAll();
        if (!$this->disableCertoBot) {
            /** @var EventDispatcher $eventDispatcher */
            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
            $eventDispatcher->dispatch(new VerificationAddedEvent($certifications));
        }
        GeneralUtility::makeInstance(CacheManager::class)->flushCachesByTags($tags);
        return $certifications;
    }

    /**
     * Assumes the validity of the verification has been validated beforehand
     *
     * @param Certification $verification
     * @param bool $accept
     * @param bool $decline
     * @param bool $revoke
     * @param string $reason
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function confirmSkillUp(
        Certification $verification,
        bool $accept = false,
        bool $decline = false,
        bool $revoke = false,
        string $reason = ''
    ): void {
        $certs = $verification->getRequestGroup()
            ? $this->certificationRepository->findByRequestGroup($verification->getRequestGroup())->toArray()
            : [$verification];
        $tags = [];

        /** @var Certification $cert */
        foreach ($certs as $cert) {
            if ($accept && !$cert->getTier3()) {
                $pointsNeeded = (int)$this->creditSettings[$cert->getLevel()];
                $price = (float)$this->creditSettings['price'];

                $packs = $this->creditPackRepository->findAvailable($cert->getBrand());
                if ($packs) {
                    $usedPackIds = [];
                    $pointsToBook = $pointsNeeded;
                    while ($pointsToBook > 0 && $packs) {
                        $pack = array_shift($packs);
                        $usedPoints = min($pointsToBook, $pack->getCurrentPoints());
                        $usedPrice = (float)$this->creditSettings['price'];

                        $usage = new VerificationCreditUsage();
                        $usage->setCreditPack($pack);
                        $usage->setPoints($usedPoints);
                        $usage->setPrice($usedPrice);
                        $usage->setVerification($cert);
                        $this->creditUsageRepository->add($usage);

                        $pack->setCurrentPoints($pack->getCurrentPoints() - $usage->getPoints());
                        $this->creditPackRepository->update($pack);

                        $usedPackIds[] = $pack->getUid();
                        $pointsToBook -= $usedPoints;
                    }
                    if ($pointsToBook !== 0) {
                        throw new LogicException('This may not happen. Credit packs modified in between?');
                    }
                    $this->logger->info(
                        'Credit usage',
                        [
                            'verificationId' => $cert->getUid(),
                            'brandId' => $cert->getBrand()->getUid(),
                            'verifierId' => $cert->getCertifier()->getUid(),
                            'userId' => $cert->getUser()->getUid(),
                            'packs' => implode(',', $usedPackIds),
                            'points/price' => $pointsNeeded . '/' . $price,
                        ]
                    );
                } elseif (!$cert->getCertifier()->getBrand()->getCreditOverdraw()) {
                    throw new LogicException('Credit did not suffice. This should never happen.');
                }
                $cert->setPoints($pointsNeeded);
                $cert->setPrice($price);
            }

            switch (true) {
                case $accept:
                    $cert->setGrantDate(new DateTime());
                    break;
                case $decline:
                    $cert->setDenyDate(new DateTime());
                    $cert->setRevokeReason($reason);
                    break;
                case $revoke:
                    $cert->setRevokeDate(new DateTime());
                    $cert->setRevokeReason($reason);
                    break;
                default:
                    break 2;
            }
            $this->certificationRepository->update($cert);
            $tags[] = $cert->getSkill()->getCacheTag($cert->getUser()->getUid());
        }
        $this->persistenceManager->persistAll();
        GeneralUtility::makeInstance(CacheManager::class)->flushCachesByTags($tags);

        if (!$this->disableCertoBot) {
            /** @var EventDispatcher $eventDispatcher */
            $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
            $eventDispatcher->dispatch(new VerificationUpdatedEvent($certs));
        }
    }

    public function cancelSkillupRequest(Certification $certification): void
    {
        $certs = $certification->getRequestGroup()
            ? $this->certificationRepository->findByRequestGroup($certification->getRequestGroup())->toArray()
            : [$certification];
        $tags = [];
        foreach ($certs as $cert) {
            $this->certificationRepository->remove($cert);
            $tags[] = $cert->getSkill()->getCacheTag($cert->getUser()->getUid());
        }
        $this->persistenceManager->persistAll();
        GeneralUtility::makeInstance(CacheManager::class)->flushCachesByTags($tags);
    }

    /**
     * @param Skill[] $skills
     * @param User|null $user
     * @param int $tier
     * @return Certifier[]
     */
    public function getVerifiersForSkills(array $skills, ?User $user, int $tier): array
    {
        if (empty($skills)) {
            return [];
        }
        $certifiers = null;
        foreach ($skills as $skill) {
            /** @var Certifier[] $skillCertifiers */
            $skillCertifiers = $this->certifierRepository->findBySkillAndTier($skill, $tier)->toArray();
            $personCertifiers = array_filter($skillCertifiers, function (Certifier $c) {
                return $c->getUser() !== null;
            });
            $testCertifiers = array_filter($skillCertifiers, function (Certifier $c) {
                return $c->getTestSystem() !== '';
            });
            usort($personCertifiers, function (Certifier $a, Certifier $b) {
                return $a->getUser()->getLastName() <=> $b->getUser()->getLastName();
            });
            if ($certifiers === null) {
                // only set this the first time we enter the loop
                $certifiers = array_merge($personCertifiers, $testCertifiers);
            } else {
                // intersect the existing allowed certifiers with the new ones for the current skill
                $newPersonCertifiers = [];
                foreach ($personCertifiers as $personCertifier) {
                    foreach ($certifiers as $certifier) {
                        if ($personCertifier->getUid() === $certifier->getUid()) {
                            $newPersonCertifiers[] = $certifier;
                        }
                    }
                }
                $newTestCertifiers = [];
                foreach ($testCertifiers as $testCertifier) {
                    foreach ($certifiers as $certifier) {
                        if ($testCertifier->getUid() === $certifier->getUid()) {
                            $newTestCertifiers[] = $certifier;
                        }
                    }
                }
                $certifiers = array_merge($newPersonCertifiers, $newTestCertifiers);
            }
        }
        if ($user && !empty($certifiers)) {
            $keysToRemove = [];
            foreach ($certifiers as $key => $certifier) {
                // remove non-public verifiers where user is not member in the same organisation
                if (!$certifier->isPublic() && !UserOrganisationsService::isUserMemberOfOrganisations([$certifier->getBrand()], $user)) {
                    // todo re-enable when TD-590 is done
                    // $keysToRemove[$key] = true;
                }
                // remove the user itself
                if ($certifier->getUser() && $certifier->getUser()->getUid() === $user->getUid()) {
                    $keysToRemove[$key] = true;
                }
            }
            if ($keysToRemove) {
                array_filter(
                    $certifiers,
                    function ($key) use ($keysToRemove) { return !isset($keysToRemove[$key]); },
                    ARRAY_FILTER_USE_KEY
                );
            }
        }
        return $certifiers;
    }

    /**
     * @param Certification[] $verifications
     * @return int
     */
    public function calculatePointsNeeded(array $verifications): int
    {
        $total = 0;
        foreach ($verifications as $verification) {
            $total += (int)$this->creditSettings[$verification->getLevel()];
        }
        return $total;
    }

    public function organisationHasEnoughCredit(int $organisationId, int $neededPoints): bool
    {
        $availablePoints = $this->creditPackRepository->getAvailableCredit($organisationId);
        return $availablePoints >= $neededPoints;
    }

    public function getBalanceForOrganisation(Brand $organisation): array
    {
        $unbalancedVerifications = $this->certificationRepository->findUnbalanced($organisation);
        $balance = array_reduce($unbalancedVerifications, function (float $sum, Certification $cert) {
            return $sum + $cert->getPrice();
        }, 0.0);
        return [
            'points' => $this->creditPackRepository->getAvailableCredit($organisation->getUid()),
            'balance' => $balance * -1,
        ];
    }

    /**
     * Move all verifications on $source skill to the successor skills $targets
     *
     * @param Skill $source
     * @param Skill[] $targets
     * @throws IllegalObjectTypeException
     */
    public function moveVerifications(Skill $source, array $targets): void
    {
        $setGroup = count($targets) > 1;
        $certs = $this->certificationRepository->findBySkill($source);
        $tags = [];
        foreach ($certs as $cert) {
            if (!$cert->getRequestGroup() && $setGroup) {
                $cert->setRequestGroup('skillSplit-' . time());
            }
            $newCert = $cert;
            foreach ($targets as $target) {
                $newCert->setSkill($target);
                $this->certificationRepository->add($newCert);
                $newCert = $cert->copy();
            }
            if ($cert->getUser()) {
                $tags[] = $source->getCacheTag($cert->getUser()->getUid());
            }
        }

        GeneralUtility::makeInstance(CacheManager::class)->flushCachesByTags($tags);
    }

    /**
     * used only for testing
     */
    public function disableCertoBot(): void
    {
        $this->disableCertoBot = true;
    }

    public function setCreditSettings(array $settings): void
    {
        $this->creditSettings = $settings;
    }
}
