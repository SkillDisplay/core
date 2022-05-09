<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein
 *
 ***/

namespace SkillDisplay\Skills\Controller;

use DateTime;
use InvalidArgumentException;
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
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class OrganisationController extends AbstractController
{
    public function listAction()
    {
        $categoryId = (int)$this->settings['category'];
        $brandRepository = $this->objectManager->get(BrandRepository::class);
        $organisations = $brandRepository->findAllByCategory($categoryId)->toArray();
        $this->view->assign('organisations', $organisations);
    }

    /**
     * @param Brand|null $organisation
     * @throws ImmediateResponseException
     * @throws PageNotFoundException
     */
    public function showAction(Brand $organisation = null)
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

            /** @var SkillPathRepository $skillPathRepository */
            $skillPathRepository = $this->objectManager->get(SkillPathRepository::class);
            $skillSets = [];

            $currentUser = $this->getCurrentUser(false);
            $inOrganisation = $currentUser &&
                UserOrganisationsService::isUserMemberOfOrganisations([$organisation], $currentUser);

            /** @var SkillPath $skillSet */
            foreach ($skillPathRepository->findSkillPathsOfBrand($organisation->getUid()) as $skillSet) {
                if ($skillSet->getVisibility() === SkillPath::VISIBILITY_PUBLIC ||
                    ($skillSet->getVisibility() === SkillPath::VISIBILITY_ORGANISATION && $inOrganisation)) {
                    $skillSet->setUserForCompletedChecks($this->getCurrentUser(false));
                    $skillSets[] = $skillSet;
                }
            }

            if ($organisation->getShowNumOfCertificates()) {
                /** @var OrganisationStatisticsRepository $statisticsRepo */
                $statisticsRepo = $this->objectManager->get(OrganisationStatisticsRepository::class);
                /** @var OrganisationStatistics $stats */
                $stats = $statisticsRepo->getOrganisationStatisticsForBrand($organisation->getUid());
            } else {
                $stats = [];
            }

            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
            $this->view->assign('skillSets', $skillSets);
            $this->view->assign('statistics', $stats);
        } else {
            // set this global variable for news filtering
            $GLOBALS['currentBrandId'] = $organisation->getUid();
            $certificationRepository = $this->objectManager->get(CertificationRepository::class);

            $verifications = $this->getVerifications($organisation);

            GeneralUtility::makeInstance(PageTitleProvider::class)->setTitle($organisation->getName());
            $this->view->assign('levelRange', range(1, $organisation->getPartnerLevel()));
            $this->view->assign('verificationsCount', $verifications[0]);
            $this->view->assign('verificationsPercentage', $verifications[1]);
            $this->view->assign('verificationTotal', $certificationRepository->countByBrand($organisation));
        }

        $this->view->assign('organisation', $organisation);
    }

    protected function getVerifications(Brand $organisation): array
    {
        $certificationRepository = $this->objectManager->get(CertificationRepository::class);
        $users = $organisation->getMembers();
        $verificationsCount = [
            3 => 0,
            2 => 0,
            1 => 0,
            4 => 0,
        ];
        /** @var User $user */
        foreach ($users as $user) {
            $certifications = $certificationRepository->findAcceptedForUser($user);
            /** @var Certification $certification */
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

    public function leaveAction(Brand $organisation)
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('');
        }
        if (!$this->view instanceof JsonView) {
            return '';
        }
        $user->getOrganisations()->detach($organisation);
        $this->objectManager->get(UserRepository::class)->update($user);
        SkillSetRelationService::registerForUpdate(SkillSetRelationService::REGISTRY_USERS, $user->getUid());

        $this->createMailMessage($mailService, $mailView, $msg);

        $mailView->assign('user', $user);
        $mailView->assign('organisation', $organisation);

        $msg->setContent($mailService->renderMail($mailView, 'organisationMemberLeft'));
        $msg->setTo($user->getEmail());

        $managers = $this->objectManager->get(UserRepository::class)->findManagers($organisation);
        /** @var User $manager */
        foreach ($managers as $manager) {
            $msg->addBcc($manager->getEmail());
        }
        $msg->send();

        return null;
    }

    public function removeMemberAction(Brand $organisation, User $user)
    {
        $loggedInUser = $this->getCurrentUser();
        if (!$loggedInUser) {
            throw new AuthenticationException('');
        }
        if (!$loggedInUser->getManagedBrands()->contains($organisation)) {
            return '{"error": "User not allowed to remove member of brand"}';
        }
        $user->getOrganisations()->detach($organisation);
        $this->objectManager->get(UserRepository::class)->update($user);
        SkillSetRelationService::registerForUpdate(SkillSetRelationService::REGISTRY_USERS, $user->getUid());

        $this->createMailMessage($mailService, $mailView, $msg);

        $mailView->assign('user', $user);
        $mailView->assign('organisation', $organisation);

        $msg->setContent($mailService->renderMail($mailView, 'organisationMemberRemoved'));
        $msg->setTo($user->getEmail());

        $managers = $this->objectManager->get(UserRepository::class)->findManagers($organisation);
        /** @var User $manager */
        foreach ($managers as $manager) {
            $msg->addBcc($manager->getEmail());
        }
        $msg->send();

        return '{"error": ""}';
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     * @throws IllegalObjectTypeException
     */
    public function joinOrganisationAction(string $code)
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('');
        }
        if ($user->isAnonymous()) {
            throw new InvalidArgumentException('User may not be anonymous', 347856234);
        }

        $invitationRepo = $this->objectManager->get(InvitationCodeRepository::class);
        /** @var InvitationCode $invitation */
        $invitation = $invitationRepo->findByCode($code)->getFirst();
        if (!$invitation) {
            $this->view->assign('success', false);
            $this->view->assign('error',
                LocalizationUtility::translate('organisation.listmy.join.error.invalidCode.text', 'skills'));
        } elseif ($invitation->getExpires() && $invitation->getExpires()->getTimestamp() < time()) {
            $this->view->assign('success', false);
            $this->view->assign('error',
                LocalizationUtility::translate('organisation.listmy.join.error.expiredCode.text', 'skills'));
        } elseif ($invitation->getUsedBy() !== null) {
            $this->view->assign('success', false);
            $this->view->assign('error',
                LocalizationUtility::translate('organisation.listmy.join.error.usedCode.text', 'skills'));
        } else {
            /** @var Brand $orga */
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
                $this->view->assign('error',
                    LocalizationUtility::translate('organisation.listmy.join.error.alreadyMember.text', 'skills'));
            } else {
                $this->view->assign('success', true);
                $this->view->assign('error', '');

                $user->getOrganisations()->attach($brand);
                SkillSetRelationService::registerForUpdate(SkillSetRelationService::REGISTRY_USERS, $user->getUid());

                $invitation->setUsedBy($user);
                $invitation->setUsedAt(new DateTime());
                $invitationRepo->update($invitation);

                $this->createMailMessage($mailService, $mailView, $msg);

                $mailView->assign('user', $user);
                $mailView->assign('organisation', $brand);

                $msg->setContent($mailService->renderMail($mailView, 'organisationMemberJoined'));
                $msg->setTo($user->getEmail());

                $managers = $this->objectManager->get(UserRepository::class)->findManagers($brand);
                /** @var User $manager */
                foreach ($managers as $manager) {
                    $msg->addBcc($manager->getEmail());
                }
                $msg->send();

                $this->objectManager->get(PersistenceManager::class)->persistAll();
                /** @var EventDispatcher $eventDispatcher */
                $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
                $eventDispatcher->dispatch(new OrganisationJoinedEvent($brand, $user));
            }
        }

        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['success', 'error']);
            return null;
        }
        return '';
    }

    /**
     * @param int $amount
     * @param Brand $brand
     * @throws IllegalObjectTypeException
     */
    public function createInvitationCodesAjaxAction(int $amount, Brand $brand)
    {
        $loggedInUser = $this->getCurrentUser(false);
        if (!$loggedInUser) {
            throw new AuthenticationException('');
        }
        if (!$loggedInUser->getManagedBrands()->contains($brand)) {
            throw new AuthenticationException('Not a manager of this organisation');
        }
        $invitationRepo = $this->objectManager->get(InvitationCodeRepository::class);
        $codes = [];
        for ($i = 0; $i < $amount; $i++) {
            $code = md5('something' . time() . $i);
            $invitation = $this->objectManager->get(InvitationCode::class);
            $invitation->setCode($code);
            $invitation->setBrand($brand);
            $invitation->setCreatedBy($this->getCurrentUser());
            $invitationRepo->add($invitation);
            $codes[] = $code;
        }
        /** @var JsonView $view */
        $view = $this->view;
        $view->setVariablesToRender(['success', 'codes']);
        $view->setConfiguration(['codes' => ['_descendAll' => []]]);
        $view->assign('success', true);
        $view->assign('codes', $codes);
    }

    /**
     * @param Brand $organisation
     * @param int $enabled
     * @param string $billingAddress
     * @param string $country
     * @param string $vatId
     */
    public function setAccountOverdrawAction(Brand $organisation, int $enabled, string $billingAddress, string $country, string $vatId)
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
            GeneralUtility::makeInstance(LogManager::class)
                ->getLogger(__CLASS__)
                ->info("Credit overdraw changed to $enabled for Brand ID " . $organisation->getUid() . ' by FE user ID ' . $user->getUid());
            $this->objectManager->get(BrandRepository::class)->update($organisation);
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
    }

    /***
     * @param Brand $organisation
     */
    public function getBillingInformationAction(Brand $organisation)
    {
        $loggedInUser = $this->getCurrentUser(false);
        if (!$loggedInUser) {
            throw new AuthenticationException('');
        }
        if (!$loggedInUser->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('Not a manager of this organisation');
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
    }

    public function managerListAction(Brand $organisation)
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('');
        }
        $validAccess = false;
        $verifiers = $this->objectManager->get(CertifierRepository::class)->findByBrandId($organisation->getUid());
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
            throw new AuthenticationException('');
        }
        if ($this->view instanceof JsonView) {
            $configuration = [
                'managers' => ['_descendAll' => User::JsonUserViewConfiguration],
            ];
            $this->view->setConfiguration($configuration);
            $this->view->setVariablesToRender(array_keys($configuration));
        }
        $managers = $this->objectManager->get(UserRepository::class)->findManagers($organisation);
        $this->view->assign('managers', $managers);
    }

    public function organisationStatisticsAction(Brand $organisation)
    {
        $loggedInUser = $this->getCurrentUser(false);
        if (!$loggedInUser) {
            throw new AuthenticationException('');
        }
        if (!$loggedInUser->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('Not a manager of this organisation');
        }
        $statisticsRepo = $this->objectManager->get(OrganisationStatisticsRepository::class);
        /** @var OrganisationStatistics $stats */
        $stats = $statisticsRepo->getOrganisationStatisticsForBrand($organisation->getUid());
        if ($stats) {
            /** @var SkillPathRepository $skillSetRepository */
            $skillSetRepository = $this->objectManager->get(SkillPathRepository::class);
            $stats->setLimitInterestToSkillSets($skillSetRepository->findAllVisible([$organisation]));
        } else {
            // there are no statistics for this brand yet, send empty one
            $stats = $this->objectManager->get(OrganisationStatistics::class);
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
    }

    public function downloadCsvStatisticsAction(Brand $organisation)
    {
        $this->verificationListAction($organisation, 'csv');
    }

    public function verificationListAction(Brand $organisation, string $type = 'csv') {
        $loggedInUser = $this->getCurrentUser(false);
        if (!$loggedInUser) {
            throw new AuthenticationException('');
        }
        if (!$loggedInUser->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('Not a manager of this organisation');
        }
        $certificationRepository = $this->objectManager->get(CertificationRepository::class);
        $certifierRepository = $this->objectManager->get(CertifierRepository::class);
        $lines = [];
        /** @var User $member */
        foreach ($organisation->getMembers() as $member) {
            foreach ($certificationRepository->findAcceptedOrDeniedByUser($member, null) as $certification) {
                $lines = $this->addVerificationEntryToArray($lines, $certification);
            }
        }
        foreach ($certifierRepository->findByBrandId($organisation->getUid()) as $certifier) {
            foreach ($certificationRepository->findAcceptedOrDeniedByUser(null, $certifier) as $certification) {
                $lines = $this->addVerificationEntryToArray($lines, $certification);
            }
        }

        $lines = $this->removeDuplicates($lines);

        usort($lines, function ($a, $b) {
            return $a[0] - $b[0];
        });

        if ($type === 'csv') {
            //set the column names
            array_unshift($lines, [
                'Uid',
                'Created',
                'Granted',
                'Denied',
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
            ]);

            $filename = 'Verifications_' . date('YmdHi') . '.csv';
            CsvService::sendCSVFile($lines, $filename);
        } else if ($type === 'json' && $this->view instanceof JsonView) {
            $this->view->assign('verifications', $lines);
            $configuration = [
                'verifications' => [
                    '_descendAll' => []
                ],
            ];
            $this->view->setConfiguration($configuration);
            $this->view->setVariablesToRender(array_keys($configuration));
        }

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
        $lines[] = [
            'uid' => $certification->getUid(),
            'created' => date('Y-m-d H:i', $certification->getCrdate()),
            'granted' => $certification->getGrantDate() ? $certification->getGrantDate()->format('Y-m-d H:i') : '',
            'denied' => $certification->getDenyDate() ? $certification->getDenyDate()->format('Y-m-d H:i') : '',
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
                : ($certification->getCertifier()->getTestSystem() ? $certification->getCertifier()->getTestSystem() : 'deleted user'))
                : 'CertoBot',
            'organisation' => $certification->getBrand() ? $certification->getBrand()->getName() : '',
            'campaign' => $certification->getCampaign() ? $certification->getCampaign()->getTitle() : '',
        ];
        return $lines;
    }
}
