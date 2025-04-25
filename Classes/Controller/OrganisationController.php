<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein
 **/

namespace SkillDisplay\Skills\Controller;

use DateTime;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\InvitationCode;
use SkillDisplay\Skills\Domain\Model\OrganisationStatistics;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\InvitationCodeRepository;
use SkillDisplay\Skills\Domain\Repository\OrganisationStatisticsRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Event\OrganisationJoinedEvent;
use SkillDisplay\Skills\Seo\PageTitleProvider;
use SkillDisplay\Skills\Service\CsvService;
use SkillDisplay\Skills\Service\SkillSetRelationService;
use SkillDisplay\Skills\Service\UserOrganisationsService;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class OrganisationController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly BrandRepository $brandRepository,
        protected readonly SkillPathRepository $skillSetRepository,
        protected readonly OrganisationStatisticsRepository $organisationStatisticsRepository,
        protected readonly CertificationRepository $certificationRepository,
        protected readonly CertifierRepository $verifierRepository,
        protected readonly InvitationCodeRepository $invitationCodeRepository,
    ) {
        parent::__construct($userRepository);
    }

    public function listAction(): ResponseInterface
    {
        $categoryId = (int)$this->settings['category'];
        $organisations = $this->brandRepository->findAllByCategory($categoryId)->toArray();
        $this->view->assign('organisations', $organisations);
        return $this->createResponse();
    }

    /**
     * @param Brand|null $organisation
     * @return ResponseInterface
     * @throws PageNotFoundException
     * @throws PropagateResponseException
     */
    public function showAction(?Brand $organisation = null): ResponseInterface
    {
        $this->assertEntityAvailable($organisation);

        if ($this->view instanceof JsonView) {
            $configuration = [
                'organisation' => [
                    '_only' => [
                        'uid',
                        'name',
                        'description',
                        'bannerPublicUrl',
                        'logoPublicUrl',
                        'url',
                        'firstCategoryTitle',
                        'memberCount',
                    ],
                ],
                'skillSets' => ['_descendAll' => SkillPath::JsonViewConfiguration],
                'statistics' => [
                    '_only' => [
                        'totalScore',
                        'sumVerifications',
                        'sumSupportedSkills',
                        'sumSkills',
                        'sumIssued',
                        'expertise',
                    ],
                    '_descend' => ['expertise' => []],
                ],
            ];

            $skillSets = [];

            $currentUser = $this->getCurrentUser(false);
            $inOrganisation = $currentUser &&
                UserOrganisationsService::isUserMemberOfOrganisations([$organisation], $currentUser);

            /** @var SkillPath $skillSet */
            foreach ($this->skillSetRepository->findSkillPathsOfBrand($organisation->getUid()) as $skillSet) {
                if ($skillSet->getVisibility() === SkillPath::VISIBILITY_PUBLIC ||
                    ($skillSet->getVisibility() === SkillPath::VISIBILITY_ORGANISATION && $inOrganisation)) {
                    if ($currentUser) {
                        $skillSet->setUserForCompletedChecks($currentUser);
                    }
                    $skillSets[] = $skillSet;
                }
            }

            if ($organisation->getShowNumOfCertificates()) {
                $stats = $this->organisationStatisticsRepository->getOrganisationStatisticsForBrand($organisation->getUid());
            } else {
                $stats = [];
            }

            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
            $this->view->assign('skillSets', $skillSets);
            $this->view->assign('statistics', $stats);
        } else {
            $verifications = $this->getVerifications($organisation);

            GeneralUtility::makeInstance(PageTitleProvider::class)->setTitle($organisation->getName());
            $this->view->assign('levelRange', range(1, $organisation->getPartnerLevel()));
            $this->view->assign('verificationsCount', $verifications[0]);
            $this->view->assign('verificationsPercentage', $verifications[1]);
            $this->view->assign('verificationTotal', $this->certificationRepository->countByBrand($organisation));
        }

        $this->view->assign('organisation', $organisation);
        return $this->createResponse();
    }

    protected function getVerifications(Brand $organisation): array
    {
        $users = $organisation->getMembers();
        $verificationsCount = [
            3 => 0,
            2 => 0,
            1 => 0,
            4 => 0,
        ];
        /** @var User $user */
        foreach ($users as $user) {
            $certifications = $this->certificationRepository->findAcceptedForUser($user);
            foreach ($certifications as $certification) {
                $verificationsCount[$certification->getLevelNumber()]++;
            }
        }
        $max = max($verificationsCount);
        $verifications = [];
        if ($max != 0) {
            $count = count($verificationsCount);
            for ($i = 1; $i <= $count; $i++) {
                $verifications[$i] = (90 / $max) * $verificationsCount[$i];
            }
        }
        return [$verificationsCount, $verifications];
    }

    public function leaveAction(Brand $organisation): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('', 6763258670);
        }
        if (!$this->view instanceof JsonView) {
            return $this->htmlResponse('');
        }
        $user->getOrganisations()->detach($organisation);
        $this->userRepository->update($user);
        SkillSetRelationService::registerForUpdate(SkillSetRelationService::REGISTRY_USERS, $user->getUid());

        $this->createMailMessage($mailService, $mailView, $msg);

        $mailView->assign('user', $user);
        $mailView->assign('organisation', $organisation);

        $msg->setContent($mailService->renderMail($mailView, 'organisationMemberLeft'));
        $msg->setTo($user->getEmail());

        $managers = $this->userRepository->findManagers($organisation);
        /** @var User $manager */
        foreach ($managers as $manager) {
            $msg->addBcc($manager->getEmail());
        }
        $msg->send();

        return $this->createResponse();
    }

    public function removeMemberAction(Brand $organisation, User $user): ResponseInterface
    {
        $loggedInUser = $this->getCurrentUser();
        if (!$loggedInUser) {
            throw new AuthenticationException('', 9251499211);
        }
        if (!$loggedInUser->getManagedBrands()->contains($organisation)) {
            return $this->jsonResponse('{"error": "User not allowed to remove member of brand"}');
        }
        $user->getOrganisations()->detach($organisation);
        $this->userRepository->update($user);
        SkillSetRelationService::registerForUpdate(SkillSetRelationService::REGISTRY_USERS, $user->getUid());

        $this->createMailMessage($mailService, $mailView, $msg);

        $mailView->assign('user', $user);
        $mailView->assign('organisation', $organisation);

        $msg->setContent($mailService->renderMail($mailView, 'organisationMemberRemoved'));
        $msg->setTo($user->getEmail());

        $managers = $this->userRepository->findManagers($organisation);
        /** @var User $manager */
        foreach ($managers as $manager) {
            $msg->addBcc($manager->getEmail());
        }
        $msg->send();

        return $this->jsonResponse('{"error": ""}');
    }

    public function joinOrganisationAction(string $code): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('', 5096069558);
        }
        if ($user->isAnonymous()) {
            throw new InvalidArgumentException('User may not be anonymous', 347856234);
        }

        $invitation = $this->invitationCodeRepository->findOneByCode($code);
        if (!$invitation) {
            $this->view->assign('success', false);
            $this->view->assign(
                'error',
                LocalizationUtility::translate('organisation.listmy.join.error.invalidCode.text', 'skills')
            );
        } elseif ($invitation->getExpires() && $invitation->getExpires()->getTimestamp() < time()) {
            $this->view->assign('success', false);
            $this->view->assign(
                'error',
                LocalizationUtility::translate('organisation.listmy.join.error.expiredCode.text', 'skills')
            );
        } elseif ($invitation->getUsedBy() !== null) {
            $this->view->assign('success', false);
            $this->view->assign(
                'error',
                LocalizationUtility::translate('organisation.listmy.join.error.usedCode.text', 'skills')
            );
        } else {
            $brand = $invitation->getBrand();
            $alreadyMember = false;
            foreach ($user->getOrganisations() as $orga) {
                if ($orga->getUid() === $brand->getUid()) {
                    $alreadyMember = true;
                    break;
                }
            }
            if ($alreadyMember) {
                $this->view->assign('success', false);
                $this->view->assign(
                    'error',
                    LocalizationUtility::translate('organisation.listmy.join.error.alreadyMember.text', 'skills')
                );
            } else {
                $this->view->assign('success', true);
                $this->view->assign('error', '');

                $user->getOrganisations()->attach($brand);
                SkillSetRelationService::registerForUpdate(SkillSetRelationService::REGISTRY_USERS, $user->getUid());

                $invitation->setUsedBy($user);
                $invitation->setUsedAt(new DateTime());
                $this->invitationCodeRepository->update($invitation);

                $this->createMailMessage($mailService, $mailView, $msg);

                $mailView->assign('user', $user);
                $mailView->assign('organisation', $brand);

                $msg->setContent($mailService->renderMail($mailView, 'organisationMemberJoined'));
                $msg->setTo($user->getEmail());

                $managers = $this->userRepository->findManagers($brand);
                /** @var User $manager */
                foreach ($managers as $manager) {
                    if (GeneralUtility::validEmail($manager->getEmail())) {
                        $msg->addBcc($manager->getEmail());
                    }
                }
                $msg->send();

                /** @var PersistenceManager $pm */
                $pm = GeneralUtility::makeInstance(PersistenceManager::class);
                $pm->persistAll();
                /** @var EventDispatcher $eventDispatcher */
                $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
                $eventDispatcher->dispatch(new OrganisationJoinedEvent($brand, $user));
            }
        }

        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['success', 'error']);
            return $this->createResponse();
        }
        return $this->htmlResponse('');
    }

    /**
     * @param int $amount
     * @param Brand $brand
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     */
    public function createInvitationCodesAjaxAction(int $amount, Brand $brand): ResponseInterface
    {
        $loggedInUser = $this->getCurrentUser(false);
        if (!$loggedInUser) {
            throw new AuthenticationException('', 7357152031);
        }
        if (!$loggedInUser->getManagedBrands()->contains($brand)) {
            throw new AuthenticationException('Not a manager of this organisation', 1790412721);
        }
        $codes = [];
        for ($i = 0; $i < $amount; $i++) {
            $code = md5('something' . time() . $i);
            $invitation = new InvitationCode();
            $invitation->setCode($code);
            $invitation->setBrand($brand);
            $invitation->setCreatedBy($this->getCurrentUser());
            $this->invitationCodeRepository->add($invitation);
            $codes[] = $code;
        }
        /** @var JsonView $view */
        $view = $this->view;
        $view->setVariablesToRender(['success', 'codes']);
        $view->setConfiguration(['codes' => ['_descendAll' => []]]);
        $view->assign('success', true);
        $view->assign('codes', $codes);
        return $this->createResponse();
    }

    public function setAccountOverdrawAction(Brand $organisation, int $enabled, string $billingAddress, string $country, string $vatId): ResponseInterface
    {
        $success = true;
        $errorMessage = '';
        $user = $this->getCurrentUser(false);
        if (!$user) {
            $errorMessage = 'No user';
            $success = false;
        }
        if ($success && !$user->getManagedBrands()->contains($organisation)) {
            $errorMessage = 'Permission denied';
            $success = false;
        }
        if ($enabled && (empty($billingAddress) || empty($country) || empty($vatId))) {
            $errorMessage = 'Missing accounting data';
            $success = false;
        }
        if ($success) {
            $organisation->setCreditOverdraw((bool)$enabled);
            if ($enabled) {
                $organisation->setBillingAddress($billingAddress);
                $organisation->setCountry($country);
                $organisation->setVatId($vatId);
            }
            $this->logger->info(sprintf(
                'Credit overdraw changed to %d for Brand ID %d by FE user ID %d',
                $enabled,
                $organisation->getUid(),
                $user->getUid()
            ));
            $this->brandRepository->update($organisation);
        }
        if ($this->view instanceof JsonView) {
            $configuration = [
                'success' => [],
                'errorMessage' => [],
            ];
            $this->view->setConfiguration($configuration);
            $this->view->setVariablesToRender(array_keys($configuration));
        }
        $this->view->assign('success', $success);
        $this->view->assign('errorMessage', $errorMessage);
        return $this->createResponse();
    }

    public function getBillingInformationAction(Brand $organisation): ResponseInterface
    {
        $loggedInUser = $this->getCurrentUser(false);
        if (!$loggedInUser) {
            throw new AuthenticationException('', 3107087013);
        }
        if (!$loggedInUser->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('Not a manager of this organisation', 1033645899);
        }
        $data = [];
        $data['address'] = $organisation->getBillingAddress();
        $data['country'] = $organisation->getCountry();
        $data['vatId'] = $organisation->getVatId();
        if ($this->view instanceof JsonView) {
            $configuration = [
                'billingInformation' => [],
            ];
            $this->view->setConfiguration($configuration);
            $this->view->setVariablesToRender(array_keys($configuration));
        }
        $this->view->assign('billingInformation', $data);
        return $this->createResponse();
    }

    public function managerListAction(Brand $organisation): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('', 5691448734);
        }
        $validAccess = false;
        $verifiers = $this->verifierRepository->findByBrandId($organisation->getUid());
        /** @var Certifier $verifier */
        foreach ($verifiers as $verifier) {
            if ($verifier->getUser()->getUid() === $user->getUid()) {
                $validAccess = true;
                break;
            }
        }
        // allow access for managers
        /** @var Brand $managedBrand */
        foreach ($user->getManagedBrands() as $managedBrand) {
            if ($managedBrand->getUid() === $organisation->getUid()) {
                $validAccess = true;
                break;
            }
        }
        if (!$validAccess) {
            throw new AuthenticationException('', 8771168510);
        }
        if ($this->view instanceof JsonView) {
            $configuration = [
                'managers' => ['_descendAll' => User::JsonUserViewConfiguration],
            ];
            $this->view->setConfiguration($configuration);
            $this->view->setVariablesToRender(array_keys($configuration));
        }
        $managers = $this->userRepository->findManagers($organisation);
        $this->view->assign('managers', $managers);
        return $this->createResponse();
    }

    public function organisationStatisticsAction(Brand $organisation, string $apiKey = ''): ResponseInterface
    {
        $loggedInUser = $this->getCurrentUser(false, $apiKey);
        if (!$loggedInUser) {
            throw new AuthenticationException('', 5586241527);
        }
        if (!$loggedInUser->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('Not a manager of this organisation', 9355847581);
        }
        $stats = $this->organisationStatisticsRepository->getOrganisationStatisticsForBrand($organisation->getUid());
        if ($stats) {
            $stats->setLimitInterestToSkillSets($this->skillSetRepository->findAllVisible([$organisation->getUid()]));
        } else {
            // there are no statistics for this brand yet, send empty one
            $stats = new OrganisationStatistics();
            $stats->setBrand($organisation);
        }
        $this->view->assign('organisationStatistics', $stats);
        if ($this->view instanceof JsonView) {
            $configuration = [
                'organisationStatistics' => OrganisationStatistics::JsonViewConfiguration,
            ];
            $this->view->setConfiguration($configuration);
            $this->view->setVariablesToRender(array_keys($configuration));
        }
        return $this->createResponse();
    }

    public function downloadCsvStatisticsAction(Brand $organisation): ResponseInterface
    {
        $this->verificationListAction($organisation, 'csv');
        return $this->createResponse();
    }

    public function verificationListAction(Brand $organisation, string $type = 'json', string $apiKey = ''): ResponseInterface
    {
        $loggedInUser = $this->getCurrentUser(false, $apiKey);
        if (!$loggedInUser) {
            throw new AuthenticationException('', 6227153446);
        }
        if (!$loggedInUser->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('Not a manager of this organisation', 5435810436);
        }
        $lines = [];
        /** @var User $member */
        foreach ($organisation->getMembers() as $member) {
            foreach ($this->certificationRepository->findAcceptedOrDeniedByUser($member, null) as $certification) {
                $lines = $this->addVerificationEntryToArray($lines, $certification);
            }
        }
        /** @var Certifier $certifier */
        foreach ($this->verifierRepository->findByBrandId($organisation->getUid()) as $certifier) {
            foreach ($this->certificationRepository->findAcceptedOrDeniedByUser(null, $certifier) as $certification) {
                $lines = $this->addVerificationEntryToArray($lines, $certification);
            }
        }

        $lines = $this->removeDuplicates($lines);

        usort($lines, fn($a, $b) => $a['uid'] - $b['uid']);

        if ($type === 'csv') {
            //set the column names
            array_unshift($lines, [
                'Uid',
                'Created',
                'Granted',
                'Skill UID',
                'Skill UUID',
                'Skill',
                'Domain Tag',
                'Level',
                'User',
                'First Name',
                'Last Name',
                'Certifier',
                'Organisation',
                'Campaign',
                'SkillSet UID',
                'SkillSet Name',
            ]);

            $filename = 'Verifications_' . date('YmdHi') . '.csv';
            CsvService::sendCSVFile($lines, $filename);
        } elseif ($type === 'json' && $this->view instanceof JsonView) {
            $this->view->assign('verifications', $lines);
            $configuration = [
                'verifications' => [
                    '_descendAll' => [],
                ],
            ];
            $this->view->setConfiguration($configuration);
            $this->view->setVariablesToRender(array_keys($configuration));
        }
        return $this->createResponse();

    }

    private function removeDuplicates(array $lines): array
    {
        $newLines = [];
        $ids = [];
        foreach ($lines as $line) {
            if (!in_array($line['uid'], $ids)) {
                $newLines[] = $line;
                $ids[] = $line['uid'];
            }
        }
        return $newLines;
    }

    private function addVerificationEntryToArray(array $lines, Certification $certification): array
    {
        $skill = $certification->getSkill();
        $skillSet = $certification->getRequestGroupParent();
        $lines[] = [
            'uid' => $certification->getUid(),
            'created' => date('Y-m-d H:i', $certification->getCrdate()),
            'granted' => $certification->getGrantDate() ? $certification->getGrantDate()->format('Y-m-d H:i') : '',
            'skillUid' => $skill ? $skill->getUid() : 0,
            'skillUUid' => $skill ? $skill->getUUId() : '',
            'skill' => $skill ? $skill->getTitle() : $certification->getSkillTitle(),
            'domainTag' => $skill && $skill->getDomainTag() ? $skill->getDomainTag()->getTitle() : '',
            'level' => LocalizationUtility::translate($certification->getLevel() . '.short', 'Skills'),
            'user' => $certification->getUser() ? $certification->getUser()->getUsername() : 'deleted user',
            'firstName' => $certification->getUser() ? $certification->getUser()->getFirstName() : 'deleted user',
            'lastName' => $certification->getUser() ? $certification->getUser()->getLastName() : 'deleted user',
            'certifier' => $certification->getCertifier()
                ? (
                    $certification->getCertifier()->getUser()
                    ? $certification->getCertifier()->getUser()->getUsername()
                    : ($certification->getCertifier()->getTestSystem() ?: 'deleted user')
                )
                : 'CertoBot',
            'organisation' => $certification->getBrand() ? $certification->getBrand()->getName() : '',
            'campaign' => $certification->getCampaign() ? $certification->getCampaign()->getTitle() : '',
            'skillSetUid' => $skillSet ? $skillSet->getUid() : 0,
            'skillSetName' => $skillSet ? $skillSet->getName() : '',
        ];
        return $lines;
    }
}
