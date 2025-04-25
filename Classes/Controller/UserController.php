<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *           Georg Ringer
 **/

namespace SkillDisplay\Skills\Controller;

use DateTime;
use Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SJBR\StaticInfoTables\Domain\Model\Country;
use SJBR\StaticInfoTables\Domain\Repository\CountryRepository;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\FileReference;
use SkillDisplay\Skills\Domain\Model\GrantedReward;
use SkillDisplay\Skills\Domain\Model\Password;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\AwardRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\GrantedRewardRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\SessionService;
use SkillDisplay\Skills\Service\ShortLinkService;
use SkillDisplay\Skills\Service\UserOrganisationsService;
use SkillDisplay\Skills\Service\UserService;
use SkillDisplay\Skills\Validation\Validator\EditUserValidator;
use TCPDF2DBarcode;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File as FalFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Type\File\FileInfo;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\IgnoreValidation;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\CMS\Fluid\View\StandaloneView;

class UserController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly SkillRepository $skillRepo,
        protected readonly UserService $userManager,
        protected readonly ShortLinkService $shortLinkService,
        protected readonly CertifierRepository $certifierRepository,
        protected readonly CountryRepository $countryRepository,
        protected readonly AwardRepository $awardRepository,
        protected readonly CertificationRepository $certificationRepository,
        protected readonly GrantedRewardRepository $grantedRewardRepository,
    ) {
        parent::__construct($userRepository);
    }

    protected function initializeAction(): void
    {
        $this->userManager->setAcceptedUserGroup($this->settings['acceptedUserGroup']);
        $this->userManager->setStoragePid($this->settings['feUserStoragePid']);
    }

    public function routeAction(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if ($user) {
            $redirect = SessionService::get('redirect');
            if ($redirect) {
                SessionService::set('redirect', null);
            } else {
                $this->uriBuilder->reset();
                $redirect = $this->settings['app'];
            }
            return new RedirectResponse($this->addBaseUriIfNecessary($redirect), 303);
        }
        $redirect = $this->request->getParsedBody()['redirect_url'] ?? $this->request->getQueryParams()['redirect_url'] ?? '';
        if ($redirect && str_contains((string)parse_url($redirect, PHP_URL_HOST), 'skilldisplay.eu')) {
            SessionService::set('redirect', $redirect);
        }

        return $this->htmlResponse('');
    }

    public function showAction(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in', 4237161679);
        }
        if ($this->view instanceof JsonView) {
            $configuration = [
                'user' => User::JsonViewConfiguration,
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
            $user = $user->toJsonData();
        }
        $this->view->assign('user', $user);
        return $this->createResponse();
    }

    public function termsAction(string $redirect = ''): ResponseInterface
    {
        SessionService::set('termsRedirect', $redirect);
        return $this->createResponse();
    }

    public function acceptTermsAction(bool $terms): ResponseInterface
    {
        if ($terms) {
            $user = $this->getCurrentUser(false);
            $user->setTermsAccepted(new DateTime());
            $this->userRepository->update($user);
            $redirect = SessionService::get('termsRedirect');
            $uri = $redirect ?: $this->uriBuilder->uriFor('edit', null, 'User');
        } else {
            $uri = $this->uriBuilder->uriFor('terms', null, 'User');
        }
        return new RedirectResponse($this->addBaseUriIfNecessary($uri), 303);
    }

    /**
     * action new
     *
     * @param User|null $newUser
     * @return ResponseInterface
     */
    #[IgnoreValidation(['argumentName' => 'newUser'])]
    public function newAction(?User $newUser = null): ResponseInterface
    {
        $currentUser = $this->getCurrentUser(false);
        if (!$newUser && $currentUser) {
            $newUser = $currentUser;
        }
        if ($shortLinkHash = $this->request->getQueryParams()['regcode'] ?? null) {
            try {
                $shortLink = $this->shortLinkService->handleShortlink($shortLinkHash);
                return (new ForwardResponse($shortLink['action']))
                    ->withControllerName($shortLink['controller'])
                    ->withArguments($shortLink['parameters']);
            } catch (InvalidArgumentException) {
                // ignore invalid hashes
            }
        }
        if ($newUser) {
            if ($newUser->isAnonymous()) {
                // reset what had been set in anonymousCreateAction
                $newUser->setFirstName('');
                $newUser->setLastName('');
                $newUser->setUsername('');
                $newUser->setPassword('');
            } else {
                $newUser = null;
            }
        }

        $this->view->assign('newUser', $newUser);
        return $this->createResponse();
    }

    /**
     * action create
     *
     * @param User $newUser
     * @return ResponseInterface
     */
    #[Validate(['validator' => 'SkillDisplay.Skills:CreateUser', 'param' => 'newUser'])]
    public function createAction(User $newUser): ResponseInterface
    {
        $newUser->setMailLanguage($this->getTSFE()->getLanguage()->getLocale()->getLanguageCode());
        $newUser->setTermsAccepted(new DateTime());
        $newUser->setAnonymous(false);
        if ($newUser->getUid()) {
            $this->userManager->update($newUser);
        } else {
            $this->userManager->add($newUser);
        }

        $code = $this->shortLinkService->createCode('userConfirm', ['uid' => $newUser->getUid()]);

        $this->createMailMessage($mailService, $mailView, $msg);

        $mailView->assign('user', $newUser);
        $mailView->assign('code', $code);

        $msg->setContent($mailService->renderMail($mailView, 'confirmation'));
        $msg->setTo($newUser->getEmail());
        $msg->send();

        $uri = $this->uriBuilder->uriFor('success', null, 'User');
        return new RedirectResponse($this->addBaseUriIfNecessary($uri), 303);
    }

    /**
     * User successfully created
     */
    public function successAction(): ResponseInterface
    {
        return $this->createResponse();
    }

    public function confirmAction(): ResponseInterface
    {
        if (!$this->request->hasArgument('uid')) {
            return new ForwardResponse('new');
        }
        try {
            $user = $this->userRepository->findDisabledByUid((int)$this->request->getArgument('uid'));
            $this->userManager->activate($user);
            // enforce session, so we get a FE cookie, otherwise autologin does not work (TYPO3 6.2.5+)
            $this->getTSFE()->fe_user->setAndSaveSessionData('skill_dummy_thing', true);
            $this->getTSFE()->fe_user->createUserSession($this->userRepository->getRawUser($user)[0]);

            $this->createMailMessage($mailService, $mailView, $msg);

            $mailView->assign('user', $user);

            $msg->setContent($mailService->renderMail($mailView, 'welcome'));
            $msg->setTo($user->getEmail());
            $msg->send();
        } catch (RuntimeException) {
            $this->addFlashMessage('', 'Invalid user', ContextualFeedbackSeverity::ERROR);
            return new ForwardResponse('new');
        }
        return $this->createResponse();
    }

    public function updateAwardSelectionAction(GrantedReward $grantedReward, int $positionIndex): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.', 3189436297);
        }
        if ($grantedReward->getUser() !== $user) {
            throw new RuntimeException('Award does not belong to user.', 7100791163);
        }
        $grantedReward->setSelectedByUser($positionIndex > 0);
        $grantedReward->setPositionIndex($positionIndex);
        $this->grantedRewardRepository->update($grantedReward);
        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['success']);
            $this->view->assign('success', ['status' => true]);
        }
        return $this->createResponse();
    }

    public function getAllAwardsAction(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.', 8504449623);
        }
        $awards = $this->grantedRewardRepository->findByUser($user)->toArray();
        $awardsToDisplay = [];
        /** @var GrantedReward $grantedAward */
        foreach ($awards as $grantedAward) {
            if (!$grantedAward->getReward() || !$grantedAward->getReward()->getSkillpath()) {
                continue;
            }
            if (!UserOrganisationsService::isSkillPathVisibleForUser($grantedAward->getReward()->getSkillpath(), $user)) {
                $grantedAward->getReward()->setLinkSkillpath(false);
            }
            $awardsToDisplay[] = $grantedAward;
        }
        if ($this->view instanceof JsonView) {
            $configuration = [
                'awards' => [
                    '_descendAll' => GrantedReward::ApiJsonViewConfiguration,
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
            $this->view->assign('awards', $awardsToDisplay);
        }
        return $this->createResponse();
    }

    public function updateProfileAction(
        string $firstName,
        string $lastName,
        string $avatarBase64,
        string $company,
        string $address,
        string $city,
        string $zip,
        string $country
    ): ResponseInterface {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.', 4320000543);
        }

        if (!$user->isLocked()) {
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
        }
        $user->setCompany($company);
        $user->setAddress($address);
        $user->setCity($city);
        $user->setZip($zip);
        $user->setCountry($country);

        $validator = new EditUserValidator();
        $result = $validator->validate($user);
        $changeValid = $result->getErrors() === [];

        $avatarGood = true;
        if ($avatarBase64 !== '') {
            $avatarConvertedForPHP = str_replace(' ', '+', $avatarBase64);
            $binaryFileContent = file_get_contents($avatarConvertedForPHP);
            $tmpFile = GeneralUtility::tempnam('skillsAvatar');
            $bytesWritten = file_put_contents($tmpFile, $binaryFileContent);
            $fileInfo = GeneralUtility::makeInstance(FileInfo::class, $tmpFile);
            $possibleExtensions = $fileInfo->getMimeExtensions();
            if (!isset($possibleExtensions[0])) {
                $avatarGood = false;
                unlink($tmpFile);
            } elseif ($bytesWritten > 0) {
                [$storageId, $objectIdentifier] = GeneralUtility::trimExplode(':', $this->settings['avatarFolder']);
                if ((int)$storageId <= 0 || empty($objectIdentifier)) {
                    throw new RuntimeException('Invalid combined identifier: ' . $this->settings['avatarFolder'], 9077933222);
                }
                /** @var ResourceFactory $resourceFactory */
                $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                $storage = $resourceFactory->getStorageObject((int)$storageId);
                if ($storage->hasFolder($objectIdentifier)) {
                    $folder = $storage->getFolder($objectIdentifier);
                } else {
                    $folder = $storage->createFolder($objectIdentifier);
                }
                $newName = $user->getUid() . '_' . strtolower($user->getLastName()) . '.' . $possibleExtensions[0];
                $avatar = $folder->addFile($tmpFile, $newName, DuplicationBehavior::REPLACE);
                if ($user->getAvatarRaw()) {
                    $this->removeFileReference($user->getAvatarRaw());
                }
                $user->setAvatar($this->createFileReferenceFromFalFileObject($avatar));
            } else {
                $avatarGood = false;
            }
        }
        $success = $changeValid && $avatarGood;
        if ($success) {
            $this->userManager->update($user);
        }
        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['success']);
            $this->view->assign('success', ['status' => $success, 'userAvatar' => $user->getUserAvatar()]);
        }
        return $this->createResponse();
    }

    public function updateSocialPlatformsAction(
        string $website,
        string $twitter,
        string $linkedin,
        string $xing,
        string $github
    ): ResponseInterface {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.', 6052011691);
        }
        $user->setWww($website);
        $user->setTwitter($twitter);
        $user->setLinkedin($linkedin);
        $user->setXing($xing);
        $user->setGithub($github);

        $this->userManager->update($user);
        if ($this->view instanceof JsonView) {
            $this->view->assign('success', true);
        } else {
            return $this->htmlResponse('');
        }
        return $this->createResponse();
    }

    public function updatePasswordAction(string $password, string $passwordRepeat, string $oldPassword): ResponseInterface
    {
        $pass = new Password();
        $pass->setPassword($password);
        $pass->setOldPassword($oldPassword);
        $pass->setPasswordRepeat($passwordRepeat);

        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.', 1255214442);
        }

        $checkResult = $this->userManager->validatePassword($pass);
        if ($checkResult != '') {
            if ($this->view instanceof JsonView) {
                $this->view->setVariablesToRender(['error']);
                $this->view->assign('error', $checkResult);
                return $this->createResponse();
            }
            return $this->htmlResponse('');

        }
        $user->setPassword($pass->getPassword());
        $this->userManager->update($user, true);

        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['error']);
            $this->view->assign('error', '');
        } else {
            return $this->htmlResponse('');
        }
        return $this->createResponse();
    }

    public function updateNotificationsAction(
        bool $mailPush,
        string $mailLanguage,
        bool $publishSkills,
        bool $newsletter
    ): ResponseInterface {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.', 2185864411);
        }
        $user->setMailPush($mailPush);
        $user->setMailLanguage($mailLanguage);
        $user->setPublishSkills($publishSkills);
        $user->setNewsletter($newsletter);

        $this->userManager->update($user);
        if ($this->view instanceof JsonView) {
            $this->view->assign('success', true);
        } else {
            return $this->htmlResponse('');
        }
        return $this->createResponse();
    }

    public function confirmEmailAction(): ResponseInterface
    {
        if (!$this->request->hasArgument('uid')) {
            return new ForwardResponse('new');
        }
        try {
            /** @var ?User $user */
            $user = $this->userRepository->findDisabledByUid((int)$this->request->getArgument('uid'));
            if (!$user) {
                throw new RuntimeException('Invalid user', 8576433016);
            }
            $this->userManager->activateEmail($user);

            $this->createMailMessage($mailService, $mailView, $msg);

            $mailView->assign('user', $user);

            $msg->setContent($mailService->renderMail($mailView, 'emailchanged'));
            $msg->setTo($user->getEmail());
            $msg->send();

            $this->view->assign('user', $user);
        } catch (RuntimeException $e) {
            $this->addFlashMessage('', 'Invalid user', ContextualFeedbackSeverity::ERROR);
            $this->logger->warning('Invalid user while confirming new email', ['exception' => $e, 'user' => $user]);
            return new ForwardResponse('new');
        }
        return $this->createResponse();
    }

    public function updateEmailAction(string $newEmail): ResponseInterface
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            throw new RuntimeException('No user logged in.', 7489080791);
        }
        if ($newEmail !== $currentUser->getEmail()) {
            $currentUser->setPendingEmail($newEmail);

            $code = $this->shortLinkService->createCode('emailConfirm', ['uid' => $currentUser->getUid()]);

            $this->createMailMessage($mailService, $mailView, $msg);

            $mailView->assign('user', $currentUser);
            $mailView->assign('code', $code);

            $msg->setContent($mailService->renderMail($mailView, 'emailconfirm'));
            // send to new email address
            try {
                $msg->setTo($newEmail);
                $msg->send();
            } catch (Exception) {
                if ($this->view instanceof JsonView) {
                    $this->view->setVariablesToRender(['error']);
                    $this->view->assign('error', 'Please enter a valid E-Mail-Address!');
                } else {
                    return $this->htmlResponse('');
                }
                return $this->createResponse();
            }

            $this->userManager->update($currentUser);
        }
        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['error']);
            $this->view->assign('error', '');
        } else {
            return $this->htmlResponse('');
        }
        return $this->createResponse();
    }

    public function deleteAction(): ResponseInterface
    {
        $currentUser = $this->getCurrentUser(false);
        $this->userManager->delete($currentUser);
        $this->view->assign('success', true);
        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['success']);
        }
        return $this->createResponse();
    }

    public function starCertifierAjaxAction(): ResponseInterface
    {
        /** @var JsonView $view */
        $view = $this->view;
        $view->setVariablesToRender(['success', 'dummy']);
        $view->assign('success', true);

        $uid = $this->request->hasArgument('uid') ? (int)$this->request->getArgument('uid')
            : (int)($this->request->getParsedBody()['uid'] ?? null);
        $star = ($this->request->hasArgument('star') ? $this->request->getArgument('star')
                : $this->request->getParsedBody()['star'] ?? null) === 'true';

        if (!$uid) {
            $view->assign('success', false);
            return $this->createResponse();
        }

        /** @var ?Certifier $certifier */
        $certifier = $this->certifierRepository->findByUid($uid);
        if (!$certifier) {
            $view->assign('success', false);
            return $this->createResponse();
        }

        $user = $this->getCurrentUser();
        if ($star) {
            $user->addFavouriteCertifier($certifier);
        } else {
            $user->removeFavouriteCertifier($certifier);
        }
        $this->userRepository->update($user);
        return $this->createResponse();
    }

    public function countriesAction(): ResponseInterface
    {
        $countries = [];
        /** @var Country $c */
        foreach ($this->countryRepository->findAll() as $c) {
            $countries[] = [
                'id' => $c->getUid(),
                'name' => $c->getShortNameEn(),
                'code' => $c->getIsoCodeA2(),
            ];
        }
        usort($countries, fn(array $a, array $b) => $a['name'] <=> $b['name']);

        if ($this->view instanceof JsonView) {
            $configuration = [
                'countries' => [
                    '_descendAll' => [],
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        $this->view->assign('countries', $countries);
        return $this->createResponse();
    }

    public function baseDataAction(): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('', 1554001798);
        }

        if ($this->view instanceof JsonView) {
            $configuration = [
                'user' => [],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        $this->view->assign('user', $user->toJsonBaseData());
        return $this->createResponse();
    }

    public function patronsAction(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('', 4074969362);
        }
        $patrons = [];
        /** @var Brand $organisation */
        foreach ($user->getOrganisations() as $organisation) {
            /** @var Brand $patron */
            foreach ($organisation->getPatrons() as $patron) {
                $patrons[$patron->getUid()] = $patron;
            }
        }
        usort($patrons, fn(Brand $a, Brand $b) => $b->getName() <=> $a->getName());
        if ($this->view instanceof JsonView) {
            $configuration = [
                'patrons' => [],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        $this->view->assign('patrons', $patrons);
        return $this->createResponse();
    }

    public function publicProfileAction(?User $user = null): ResponseInterface
    {
        $profile = [];
        $configuration = [
            'publicProfile' => [
                'organisations' => [
                    '_descendAll' => Brand::JsonViewMinimalConfiguration,
                ],
                'awards' => [
                    '_descendAll' => GrantedReward::ApiJsonViewConfiguration,
                ],
                'monthlyActivity' => [],
            ],
        ];
        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        if ($user === null) {
            $profile = [
                'id' => 0,
                'message' => 'A user with this id does not exist.',
            ];
            $this->view->assign('publicProfile', $profile);
            return $this->createResponse();
        }
        if ($this->getCurrentUser(false) === $user || $user->getPublishSkills()) {
            $profile = $this->getPublicProfile($user);
        } elseif ($this->view instanceof JsonView) {
            $profile = [
                'id' => $user->getUid(),
                'message' => 'The requested user does not want his profile to be published publicly.',
            ];
        }
        $this->view->assign('publicProfile', $profile);
        return $this->createResponse();
    }

    private function getPublicProfile(User $user): array
    {
        $acceptedCertifications = $user->getSkillUpStats();
        $selectedRewards = $this->grantedRewardRepository->getSelectedRewardsByUser($user);

        return [
            'id' => $user->getUid(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'isVerified' => $user->isLocked(),
            'website' => $user->getWww(),
            'twitter' => $user->getTwitter(),
            'linkedin' => $user->getLinkedin(),
            'xing' => $user->getXing(),
            'github' => $user->getGithub(),
            'userAvatar' => $user->getUserAvatar(),
            'skillPointData' => [
                'self' => $acceptedCertifications[3],
                'education' => $acceptedCertifications[2],
                'business' => $acceptedCertifications[4],
                'certificate' => $acceptedCertifications[1],
            ],
            'organisations' => $user->getOrganisations(),
            'awards' => $selectedRewards,
            'monthlyActivity' => $user->getMonthlyActivity(),
        ];
    }

    public function publicProfileVerificationsAction(User $user, int $type = 0): ResponseInterface
    {
        $currentUser = $this->getCurrentUser(false);
        if (!$currentUser) {
            throw new AuthenticationException('', 1327049855);
        }
        if (!$user->getPublishSkills() && $currentUser !== $user) {
            throw new AuthenticationException('', 3813980212);
        }
        if ($type === Certification::TYPE_GROUPED_BY_DATE) {
            if ($this->view instanceof JsonView) {
                $configuration = [
                    'verifications' => [
                        '_descendAll' => Certification::JsonViewConfiguration,
                    ],
                ];
                $this->view->setVariablesToRender(array_keys($configuration));
                $this->view->setConfiguration($configuration);
            }
            $verifications = $this->getVerificationsGroupedByDate($user, $currentUser);
            $this->view->assign('verifications', $verifications);
        } elseif ($type === Certification::TYPE_GROUPED_BY_BRAND) {
            if ($this->view instanceof JsonView) {
                $configuration = [
                    'brands' => [
                        'tags' => [
                            '_descendAll' => [
                                'skills' => [],
                            ],
                        ],
                    ],
                ];
                $this->view->setVariablesToRender(array_keys($configuration));
                $this->view->setConfiguration($configuration);
            }
            $verifications = $this->groupVerificationsByBrandAndDomain($user, $currentUser);
            $this->view->assign('brands', $verifications);
        }
        return $this->createResponse();
    }

    private function getVerificationsGroupedByDate(User $user, ?User $currentUser): array
    {
        $groups = $this->certificationRepository->findByUser($user);
        return array_values(array_filter(array_map(function (array $group) use ($currentUser) {
            /** @var Certification $verification */
            $verification = $group['certs'][0];
            // validate if the currently logged-in user may see this skill of the shown $user
            if (!$verification->getSkill() || !UserOrganisationsService::isSkillVisibleForUser($verification->getSkill(), $currentUser)) {
                return null;
            }
            if (!$verification->getGrantDate() || $verification->getDenyDate() || $verification->getRevokeDate()) {
                return null;
            }
            $jsonData = $verification->toJsonData();
            $jsonData['skillCount'] = count($group['certs']);
            unset($jsonData['crdate']);
            unset($jsonData['denyDate']);
            unset($jsonData['revokeDate']);
            unset($jsonData['user']);
            unset($jsonData['verifier']);
            unset($jsonData['comment']);
            unset($jsonData['reason']);
            unset($jsonData['skillSet']);
            unset($jsonData['skills']);
            return $jsonData;
        }, $groups)));
    }

    public function downloadPublicProfilePdfAction(): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if ($user === null) {
            $profile = [
                'id' => 0,
                'message' => 'A user with this id does not exist.',
            ];
            return $this->jsonResponse(json_encode($profile));
        }

        $dateVerifications = $this->getVerificationsGroupedByDate($user, $user);
        $totalCount = 0;
        $selfCount = 0;
        $educationCount = 0;
        $businessCount = 0;
        $certCount = 0;
        foreach ($dateVerifications as $v) {
            $totalCount += $v['skillCount'];
            if ($v['type'] == 3) {
                $selfCount += $v['skillCount'];
            } elseif ($v['type'] == 2) {
                $educationCount += $v['skillCount'];
            } elseif ($v['type'] == 4) {
                $businessCount += $v['skillCount'];
            } elseif ($v['type'] == 1) {
                $certCount += $v['skillCount'];
            }
        }
        $profile = $this->getPublicProfile($user);
        $verifications = $this->groupVerificationsByBrandAndDomain($user, $user);
        /** @var StandaloneView $pdfView */
        $pdfView = GeneralUtility::makeInstance(StandaloneView::class);
        $pdfView->setTemplatePathAndFilename('EXT:skills/Resources/Private/PdfTemplates/PublicProfile.html');
        $pdfView->assign('user', $user);
        $avatarFile = $user->getAvatar();
        $userImage = $avatarFile instanceof \TYPO3\CMS\Extbase\Domain\Model\FileReference
            ? $avatarFile->getOriginalResource() : $avatarFile;
        $url = $userImage->getForLocalProcessing(false);
        $pdfView->assign('imgUrl', $url);
        $uri = $this->settings['app'];
        $userProfileUrl = $uri . 'publicProfile/' . $user->getUid();
        $barcodePath = GeneralUtility::tempnam('barcode', '.png');
        $barcodeobj = new TCPDF2DBarcode($userProfileUrl, 'QRCODE,M');
        file_put_contents($barcodePath, $barcodeobj->getBarcodePngData(60, 60));
        $absIconPath = ExtensionManagementUtility::extPath('skills') . 'Resources/Private/Icons/';
        $pdfView->assign('code', $barcodePath);
        $pdfView->assign('profile', $profile);
        $pdfView->assign('currentDate', date('M jS Y'));
        $pdfView->assign('verifications', $verifications);
        $pdfView->assign('totalCount', $totalCount);
        $pdfView->assign('selfCount', $selfCount);
        $pdfView->assign('educationCount', $educationCount);
        $pdfView->assign('businessCount', $businessCount);
        $pdfView->assign('certCount', $certCount);
        $pdfView->assign('mailSvg', $absIconPath . 'notification.svg');
        $pdfView->assign('webSvg', $absIconPath . 'website.svg');
        $pdfView->assign('grantedSelfSvg', $absIconPath . 'grantedSelf.svg');
        $pdfView->assign('defaultSelfSvg', $absIconPath . 'defaultSelf.svg');
        $pdfView->assign('grantedEducationSvg', $absIconPath . 'grantedEducation.svg');
        $pdfView->assign('defaultEducationSvg', $absIconPath . 'defaultEducation.svg');
        $pdfView->assign('grantedBusinessSvg', $absIconPath . 'grantedBusiness.svg');
        $pdfView->assign('defaultBusinessSvg', $absIconPath . 'defaultBusiness.svg');
        $pdfView->assign('grantedCertSvg', $absIconPath . 'grantedCert.svg');
        $pdfView->assign('defaultCertSvg', $absIconPath . 'defaultCert.svg');
        $pdfView->assign('awardBronzeSvg', $absIconPath . 'awardBronze.svg');
        $pdfView->assign('awardSilverSvg', $absIconPath . 'awardSilver.svg');
        $pdfView->assign('awardGoldSvg', $absIconPath . 'awardGold.svg');
        $pdfView->assign('awardPlatinumSvg', $absIconPath . 'awardPlatinum.svg');
        $filename = $user->getFirstName() . '_' . $user->getLastName() . '_PublicProfile.pdf';
        $filepath = GeneralUtility::tempnam('publicprofile', '.pdf');
        $tmpPath = GeneralUtility::tempnam('publicprofile', '.html');
        file_put_contents($tmpPath, $pdfView->render());
        exec('weasyprint ' . $tmpPath . ' ' . $filepath);

        if (file_exists($filepath)) {
            $headers = [
                'Pragma' => 'public',
                'Expires' => 0,
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Content-Description' => 'File Transfer',
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename,
                'Content-Transfer-Encoding' => 'binary',
                'Content-Length' => filesize($filepath),
            ];

            // send headers
            foreach ($headers as $header => $data) {
                header($header . ': ' . $data);
            }
            readfile($filepath);
            // unlink files
            unlink($filepath);
            unlink($tmpPath);
            unlink($barcodePath);
            exit();
        }
        if ($this->view instanceof JsonView) {
            $profile = [
                'id' => $user->getUid(),
                'message' => 'The requested user does not want his profile to be published publicly.',
            ];
            return $this->jsonResponse(json_encode($profile));
        }
        return $this->htmlResponse('');
    }

    private function groupVerificationsByBrandAndDomain(User $user, ?User $currentUser): array
    {
        $groupedByBrandAndDomain = [];

        /** @var Certification $verification */
        foreach ($this->certificationRepository->findAcceptedForUser($user) as $verification) {
            $skill = $verification->getSkill();
            if (!$skill || !UserOrganisationsService::isSkillVisibleForUser($skill, $currentUser)) {
                continue;
            }

            /** @var ?Brand $brand */
            $brand = $skill->getBrands()->getArray()[0] ?? null;
            if (!$brand) {
                continue;
            }
            $brandId = $brand->getUid();
            if (!isset($groupedByBrandAndDomain[$brandId])) {
                $groupedByBrandAndDomain[$brandId] = [
                    '_brandTitle' => $brand->getName(),
                    'tags' => [],
                ];
            }

            $domainTag = $skill->getDomainTag();
            $domainTagId = $domainTag ? $domainTag->getUid() : 0;

            if (!isset($groupedByBrandAndDomain[$brandId]['tags'][$domainTagId])) {
                $groupedByBrandAndDomain[$brandId]['tags'][$domainTagId] = [
                    '_domain' => $domainTagId ? $domainTag->getTitle() : '-',
                    'skills' => [],
                ];
            }
            $skillId = $skill->getUid();
            if (!isset($groupedByBrandAndDomain[$brandId]['tags'][$domainTagId]['skills'][$skillId])) {
                $groupedByBrandAndDomain[$brandId]['tags'][$domainTagId]['skills'][$skillId] = [
                    'skill' => [
                        'uid' => $skillId,
                        'title' => $skill->getTitle(),
                        'description' => $skill->getDescription(),
                    ],
                    'levels' => [],
                ];
            }
            $groupedByBrandAndDomain[$brandId]['tags'][$domainTagId]['skills'][$skillId]['levels'][] = $verification->getLevelNumber();
        }

        // sort skills and remove uids from array keys to get a real JSON array
        array_walk($groupedByBrandAndDomain, function (array &$brand) {
            array_walk($brand['tags'], function (array &$skillTags) {
                usort($skillTags['skills'], fn(array $a, array $b) => $a['skill']['title'] <=> $b['skill']['title']);
            });
            usort($brand['tags'], fn(array $a, array $b) => $a['_domain'] <=> $b['_domain']);
        });
        usort($groupedByBrandAndDomain, fn(array $a, array $b) => $a['_brandTitle'] <=> $b['_brandTitle']);
        return $groupedByBrandAndDomain;
    }

    public function anonymousRequestAction(): ResponseInterface
    {
        $redirect = $this->request->getParsedBody()['redirect_url'] ?? $this->request->getQueryParams()['redirect_url'] ?? null;
        if ($redirect && str_contains((string)parse_url((string)$redirect, PHP_URL_HOST), 'skilldisplay.eu')) {
            SessionService::set('redirect', $redirect);
        }
        return $this->createResponse();
    }

    public function anonymousCreateAction(): ResponseInterface
    {
        $tsfe = $this->getTSFE();
        if ($this->request->getMethod() === 'POST') {
            $user = $this->getCurrentUser(false);
            if ($user) {
                return new ForwardResponse('route');
            }

            $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
            $data = [
                'anonymous' => 1,
                'first_name' => 'John',
                'last_name' => 'Skiller',
                'username' => 'demo' . $now . '@example.com',
                'password' => 'none',
                'tx_extbase_type' => 'Tx_Skills_User',
                'pid' => $this->settings['feUserStoragePid'],
                'usergroup' => $this->settings['acceptedUserGroup'],
                'tstamp' => $now,
                'crdate' => $now,
            ];

            $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('fe_users');
            $qb->insert('fe_users', $data);
            $uid = $qb->lastInsertId('fe_users');
            $data['uid'] = $uid;

            $this->autologin($data);

            $url = $_GET['redirect_url'] ?? $this->settings['app'];
        } else {
            $url = $tsfe->cObj->typoLink_URL(
                ['parameter' => (int)$this->settings['pids']['login']]
            );
        }

        return new RedirectResponse($this->addBaseUriIfNecessary($url), 303);
    }

    public function getOrganizationsForCurrentUserAction(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('', 2533347348);
        }
        if ($this->view instanceof JsonView) {
            $configuration = [
                'organizations' => [
                    '_descendAll' => Brand::JsonViewMinimalConfiguration,
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        $organizations = $user->getOrganisations()->getArray();
        $this->view->assign('organizations', $organizations);
        return $this->createResponse();
    }

    protected function createFileReferenceFromFalFileObject(FalFile $file): FileReference
    {
        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $falFileReference = $resourceFactory->createFileReferenceObject(
            [
                'uid_local' => $file->getUid(),
                'uid_foreign' => uniqid('NEW_'),
                'uid' => uniqid('NEW_'),
                'crop' => null,
            ]
        );
        $fileReference = new FileReference();
        $fileReference->setOriginalResource($falFileReference);
        return $fileReference;
    }

    private function removeFileReference(\TYPO3\CMS\Extbase\Domain\Model\FileReference $ref): void
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $qb->delete('sys_file_reference')
            ->where('uid = ' . $ref->getOriginalResource()->getUid())
            ->executeStatement();
    }

    private function autologin(array $user): void
    {
        $userSessionManager = UserSessionManager::create('FE');
        $sessionData = $this->getTSFE()->fe_user->getSession()->getData();
        $session = $this->getTSFE()->fe_user->createUserSession($user);
        $session->overrideData($sessionData);
        $userSessionManager->updateSession($session);
        $this->getTSFE()->fe_user->enforceNewSessionId();
    }
}
