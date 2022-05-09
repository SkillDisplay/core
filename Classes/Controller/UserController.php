<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *           Georg Ringer
 *
 ***/

namespace SkillDisplay\Skills\Controller;

use DateTime;
use Exception;
use InvalidArgumentException;
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
use SkillDisplay\Skills\Domain\Model\Tag;
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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderReadPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderWritePermissionsException;
use TYPO3\CMS\Core\Resource\File as FalFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Type\File\FileInfo;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Fluid\View\StandaloneView;

class UserController extends AbstractController
{
    protected UserRepository $userRepository;

    protected SkillRepository $skillRepo;

    protected UserService $userManager;

    protected ShortLinkService $shortLinkService;

    protected CertifierRepository $certifierRepository;

    protected CountryRepository $countryRepository;

    protected AwardRepository $awardRepository;

    protected CertificationRepository $certificationRepository;

    public function __construct(
        SkillRepository         $skillRepo,
        UserService             $userManager,
        UserRepository          $userRepository,
        ShortLinkService        $shortLinkService,
        CertifierRepository     $certifierRepository,
        CountryRepository       $countryRepository,
        AwardRepository         $awardRepository,
        CertificationRepository $certificationRepository
    )
    {
        $this->skillRepo = $skillRepo;
        $this->userManager = $userManager;
        $this->userRepository = $userRepository;
        $this->shortLinkService = $shortLinkService;
        $this->certifierRepository = $certifierRepository;
        $this->countryRepository = $countryRepository;
        $this->awardRepository = $awardRepository;
        $this->certificationRepository = $certificationRepository;
    }

    protected function initializeAction()
    {
        parent::initializeAction();
        $this->userManager->setAcceptedUserGroup($this->settings['acceptedUserGroup']);
        $this->userManager->setStoragePid($this->settings['feUserStoragePid']);
    }

    /**
     * @return string
     * @throws StopActionException
     */
    public function routeAction()
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
            $this->redirectToUri($redirect);
        } else {
            $redirect = GeneralUtility::_GP('redirect_url');
            if ($redirect && strpos((string)parse_url($redirect, PHP_URL_HOST), 'skilldisplay.eu') !== false) {
                SessionService::set('redirect', $redirect);
            }
        }
        return '';
    }

    public function showAction()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in');
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
    }

    /**
     * @param string $redirect
     */
    public function termsAction(string $redirect = '')
    {
        SessionService::set('termsRedirect', $redirect);
    }

    /**
     * @param bool $terms
     * @throws StopActionException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function acceptTermsAction(bool $terms)
    {
        if ($terms) {
            $user = $this->getCurrentUser(false);
            $user->setTermsAccepted(new DateTime());
            $this->userRepository->update($user);
            $redirect = SessionService::get('termsRedirect');
            if ($redirect) {
                $this->redirectToUri($redirect);
            } else {
                $this->redirect('edit');
            }
        }
        $this->redirect('terms');
    }

    /**
     * action new
     *
     * @param User|null $newUser
     * @TYPO3\CMS\Extbase\Annotation\IgnoreValidation("newUser")
     * @throws StopActionException
     */
    public function newAction(User $newUser = null)
    {
        $currentUser = $this->getCurrentUser(false);
        if (!$newUser && $currentUser) {
            $newUser = $currentUser;
        }
        if ($shortLinkHash = GeneralUtility::_GET('code')) {
            try {
                $shortlink = $this->shortLinkService->handleShortlink($shortLinkHash);
                $this->forward($shortlink['action'], $shortlink['controller'], null, $shortlink['parameters']);
            } catch (InvalidArgumentException $e) {
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
    }

    /**
     * action create
     *
     * @param User $newUser
     * @return void
     * @TYPO3\CMS\Extbase\Annotation\Validate(validator="SkillDisplay.Skills:CreateUser", param="newUser")
     * @throws StopActionException
     */
    public function createAction(User $newUser)
    {
        $newUser->setMailLanguage($this->getTSFE()->getLanguage()->getTwoLetterIsoCode());
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

        $this->redirect('success');
    }

    /**
     * User successfully created
     */
    public function successAction()
    {
    }

    /**
     * @throws StopActionException
     * @throws NoSuchArgumentException
     */
    public function confirmAction()
    {
        if (!$this->request->hasArgument('uid')) {
            $this->forward('new');
        }
        try {
            /** @var User $user */
            $user = $this->userRepository->findDisabledByUid((int)$this->request->getArgument('uid'));
            $this->userManager->activate($user);
            // enforce session so we get a FE cookie, otherwise autologin does not work (TYPO3 6.2.5+)
            $this->getTSFE()->fe_user->setAndSaveSessionData('skill_dummy_thing', true);
            $this->getTSFE()->fe_user->createUserSession($this->userRepository->getRawUser($user)[0]);

            $this->createMailMessage($mailService, $mailView, $msg);

            $mailView->assign('user', $user);

            $msg->setContent($mailService->renderMail($mailView, 'welcome'));
            $msg->setTo($user->getEmail());
            $msg->send();
        } catch (RuntimeException $e) {
            $this->addFlashMessage('', 'Invalid user', FlashMessage::ERROR);
            $this->forward('new');
        }
    }

    /**
     * @param GrantedReward $grantedReward
     * @param int $positionIndex
     * @return void
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function updateAwardSelectionAction(GrantedReward $grantedReward, int $positionIndex) {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.');
        }
        if ($grantedReward->getUser() !== $user) {
            throw new RuntimeException('Award does not belong to user.');
        }
        /** @var GrantedRewardRepository $grantedRewardRepository */
        $grantedRewardRepository = GeneralUtility::makeInstance(GrantedRewardRepository::class);
        $grantedReward->setSelectedByUser($positionIndex > 0);
        $grantedReward->setPositionIndex($positionIndex);
        $grantedRewardRepository->update($grantedReward);
        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['success']);
            $this->view->assign('success', ['status' => true]);
        }
    }

    public function getAllAwardsAction() {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.');
        }
        /** @var GrantedRewardRepository $grantedRewardRepository */
        $grantedRewardRepository = GeneralUtility::makeInstance(GrantedRewardRepository::class);
        $awards = $grantedRewardRepository->findByUser($user)->toArray();
        if ($this->view instanceof JsonView) {
            $configuration = [
                'awards' => [
                    '_descendAll' => GrantedReward::ApiJsonViewConfiguration,
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
            $this->view->assign('awards', $awards);
        }
    }

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $avatarBase64
     * @param string $company
     * @param string $address
     * @param string $city
     * @param string $zip
     * @param string $country
     * @return string|null
     * @throws ExistingTargetFolderException
     * @throws InsufficientFolderAccessPermissionsException
     * @throws InsufficientFolderReadPermissionsException
     * @throws InsufficientFolderWritePermissionsException
     */
    public function updateProfileAction(
        string $firstName,
        string $lastName,
        string $avatarBase64,
        string $company,
        string $address,
        string $city,
        string $zip,
        string $country
    )
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.');
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

        /** @var EditUserValidator $validator */
        $validator = $this->objectManager->get(EditUserValidator::class, []);
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
                    throw new RuntimeException('Invalid combined identifier: ' . $this->settings['avatarFolder']);
                }
                $storage = GeneralUtility::makeInstance(ResourceFactory::class)->getStorageObject((int)$storageId);
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
        return null;
    }

    /**
     * @param string $website
     * @param string $twitter
     * @param string $linkedin
     * @param string $xing
     * @param string $github
     * @return string|null
     */
    public function updateSocialPlatformsAction(
        string $website,
        string $twitter,
        string $linkedin,
        string $xing,
        string $github
    )
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.');
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
            return '';
        }
        return null;
    }

    /**
     * @param string $password
     * @param string $passwordRepeat
     * @param string $oldPassword
     * @return string|null
     */
    public function updatePasswordAction(string $password, string $passwordRepeat, string $oldPassword)
    {
        $pass = new Password();
        $pass->setPassword($password);
        $pass->setOldPassword($oldPassword);
        $pass->setPasswordRepeat($passwordRepeat);

        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.');
        }

        $checkResult = $this->userManager->validatePassword($pass);
        if ($checkResult != '') {
            if ($this->view instanceof JsonView) {
                $this->view->setVariablesToRender(['error']);
                $this->view->assign('error', $checkResult);
                return null;
            } else {
                return '';
            }
        }
        $user->setPassword($pass->getPassword());
        $this->userManager->update($user, true);

        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['error']);
            $this->view->assign('error', '');
        } else {
            return '';
        }
        return null;
    }

    /**
     * @param bool $mailPush
     * @param string $mailLanguage
     * @param bool $publishSkills
     * @param bool $newsletter
     * @return string|null
     */
    public function updateNotificationsAction(
        bool   $mailPush,
        string $mailLanguage,
        bool   $publishSkills,
        bool   $newsletter
    )
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new RuntimeException('No user logged in.');
        }
        $user->setMailPush($mailPush);
        $user->setMailLanguage($mailLanguage);
        $user->setPublishSkills($publishSkills);
        $user->setNewsletter($newsletter);

        $this->userManager->update($user);
        if ($this->view instanceof JsonView) {
            $this->view->assign('success', true);
        } else {
            return '';
        }
        return null;
    }

    /**
     * @throws NoSuchArgumentException
     * @throws StopActionException
     */
    public function confirmEmailAction()
    {
        if (!$this->request->hasArgument('uid')) {
            $this->forward('new');
        }
        try {
            /** @var User $user */
            $user = $this->userRepository->findDisabledByUid((int)$this->request->getArgument('uid'));
            if (!$user) {
                throw new RuntimeException('Invalid user');
            }
            $this->userManager->activateEmail($user);

            $this->createMailMessage($mailService, $mailView, $msg);

            $mailView->assign('user', $user);

            $msg->setContent($mailService->renderMail($mailView, 'emailchanged'));
            $msg->setTo($user->getEmail());
            $msg->send();

            $this->view->assign('user', $user);
        } catch (RuntimeException $e) {
            $this->addFlashMessage('', 'Invalid user', FlashMessage::ERROR);
            $this->logger->warning('Invalid user while confirming new email', ['exception' => $e, 'user' => $user]);
            $this->forward('new');
        }
    }

    /**
     * @param string $newEmail
     * @return string|null
     */
    public function updateEmailAction(string $newEmail)
    {
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) {
            throw new RuntimeException('No user logged in.');
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
            } catch (Exception $e) {
                if ($this->view instanceof JsonView) {
                    $this->view->setVariablesToRender(['error']);
                    $this->view->assign('error', 'Please enter a valid E-Mail-Address!');
                } else {
                    return '';
                }
                return null;
            }

            $this->userManager->update($currentUser);
        }
        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['error']);
            $this->view->assign('error', '');
        } else {
            return '';
        }
        return null;
    }

    /**
     * @throws IllegalObjectTypeException
     * @throws NoSuchArgumentException
     * @throws UnknownObjectException
     */
    public function starCertifierAjaxAction()
    {
        /** @var JsonView $view */
        $view = $this->view;
        $view->setVariablesToRender(['success', 'dummy']);
        $view->assign('success', true);

        $uid = $this->request->hasArgument('uid') ? (int)$this->request->getArgument('uid')
            : (int)GeneralUtility::_POST('uid');
        $star = ($this->request->hasArgument('star') ? $this->request->getArgument('star')
                : GeneralUtility::_POST('star')) === 'true';

        if (!$uid) {
            $view->assign('success', false);
            return;
        }

        /** @var Certifier $certifier */
        $certifier = $this->certifierRepository->findByUid($uid);
        if (!$certifier) {
            $view->assign('success', false);
            return;
        }

        $user = $this->getCurrentUser();
        if ($star) {
            $user->addFavouriteCertifier($certifier);
        } else {
            $user->removeFavouriteCertifier($certifier);
        }
        $this->userRepository->update($user);
    }

    public function countriesAction()
    {
        $countries = [];
        /** @var Country $c */
        foreach ($this->countryRepository->findAll() as $c) {
            $countries[] = [
                'id' => $c->getUid(),
                'name' => $c->getShortNameEn(),
                'code' => $c->getIsoCodeA2()
            ];
        }
        usort($countries, function (array $a, array $b) {
            return $a['name'] <=> $b['name'];
        });

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
    }

    public function baseDataAction()
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('');
        }

        if ($this->view instanceof JsonView) {
            $configuration = [
                'user' => [],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        $this->view->assign('user', $user->toJsonBaseData());
    }

    public function patronsAction()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('');
        }
        $patrons = [];
        /** @var Brand $organisation */
        foreach ($user->getOrganisations() as $organisation) {
            /** @var Brand $patron */
            foreach ($organisation->getPatrons() as $patron) {
                $patrons[$patron->getUid()] = $patron;
            }
        }
        usort($patrons, function (Brand $a, Brand $b) {
            return $b->getName() <=> $a->getName();
        });
        if ($this->view instanceof JsonView) {
            $configuration = [
                'patrons' => [],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        $this->view->assign('patrons', $patrons);
    }

    public function publicProfileAction(User $user = null)
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
            return;
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
    }

    private function getPublicProfile(User $user): array
    {
        $acceptedCertifications = $user->getSkillUpStats();
        $grantedRewardsRepository = GeneralUtility::makeInstance(GrantedRewardRepository::class);
        $selectedRewards = $grantedRewardsRepository->getSelectedRewardsByUser($user);

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

    public function publicProfileVerificationsAction(User $user, int $type = 0)
    {
        $currentUser = $this->getCurrentUser(false);
        if (!$currentUser) {
            throw new AuthenticationException('');
        }
        if (!$user->getPublishSkills() && $currentUser !== $user) {
            throw new AuthenticationException('');
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
    }

    private function getVerificationsGroupedByDate(User $user, ?User $currentUser): array
    {
        $groups = $this->certificationRepository->findByUser($user);
        return array_values(array_filter(array_map(function (array $group) use ($currentUser) {
            /** @var Certification $verification */
            $verification = $group['certs'][0];
            // validate if the currently logged in user may see this skill of the shown $user
            if (!$verification->getSkill() || !UserOrganisationsService::isSkillVisibleForUser($verification->getSkill(), $currentUser)) {
                return null;
            }
            $jsonData = $verification->toJsonData();
            if ($jsonData['grantDate'] === null) {
                return null;
            }
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

    public function downloadPublicProfilePdfAction()
    {
        $user = $this->getCurrentUser(false);
        if ($user === null) {
            $profile = [
                'id' => 0,
                'message' => 'A user with this id does not exist.',
            ];
            return json_encode($profile);
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
        } elseif ($this->view instanceof JsonView) {
            $profile = [
                'id' => $user->getUid(),
                'message' => 'The requested user does not want his profile to be published publicly.',
            ];
            return json_encode($profile);
        }
        return '';
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

            /** @var Brand $brand */
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

            /** @var Tag $domainTag */
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
                usort($skillTags['skills'], function (array $a, array $b) {
                    return $a['skill']['title'] <=> $b['skill']['title'];
                });
                $skillTags['skills'] = array_values($skillTags['skills']);
            });
            usort($brand['tags'], function (array $a, array $b) {
                return $a['_domain'] <=> $b['_domain'];
            });
            $brand['tags'] = array_values($brand['tags']);
        });
        usort($groupedByBrandAndDomain, function (array $a, array $b) {
            return $a['_brandTitle'] <=> $b['_brandTitle'];
        });
        return array_values($groupedByBrandAndDomain);
    }

    public function anonymousRequestAction()
    {
        $redirect = GeneralUtility::_GP('redirect_url');
        if ($redirect && strpos((string)parse_url($redirect, PHP_URL_HOST), 'skilldisplay.eu') !== false) {
            SessionService::set('redirect', $redirect);
        }
    }

    /**
     * @throws StopActionException
     */
    public function anonymousCreateAction()
    {
        $tsfe = $this->getTSFE();
        if ($GLOBALS['TYPO3_REQUEST']->getMethod() === 'POST') {
            $user = $this->getCurrentUser(false);
            if ($user) {
                $this->forward('route');
            }

            $data = [
                'anonymous' => 1,
                'first_name' => 'John',
                'last_name' => 'Skiller',
                'username' => 'demo' . $GLOBALS['EXEC_TIME'] . '@example.com',
                'password' => 'none',
                'tx_extbase_type' => 'Tx_Skills_User',
                'pid' => $this->settings['feUserStoragePid'],
                'usergroup' => $this->settings['acceptedUserGroup'],
                'tstamp' => $GLOBALS['EXEC_TIME'],
                'crdate' => $GLOBALS['EXEC_TIME'],
            ];

            $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('fe_users');
            $qb->insert('fe_users', $data);
            $uid = $qb->lastInsertId('fe_users');
            $data['uid'] = $uid;

            // ensure a session cookie is set (in case there is no session yet)
            $tsfe->fe_user->setAndSaveSessionData('dummy', true);
            // create the session (destroys all existing session data in the session backend)
            $tsfe->fe_user->createUserSession($data);
            // write the session data again to the session backend; preserves what was there before
            $tsfe->fe_user->setAndSaveSessionData('dummy', true);

            $url = $_GET['redirect_url'] ?? $this->settings['app'];
        } else {
            $url = $tsfe->cObj->typoLink_URL(
                ['parameter' => (int)$this->settings['pids']['login']]
            );
        }

        HttpUtility::redirect($url, HttpUtility::HTTP_STATUS_303);
    }

    public function getOrganizationsForCurrentUserAction()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('');
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
    }

    protected function createFileReferenceFromFalFileObject(FalFile $file): FileReference
    {
        $falFileReference = GeneralUtility::makeInstance(ResourceFactory::class)->createFileReferenceObject(
            [
                'uid_local' => $file->getUid(),
                'uid_foreign' => uniqid('NEW_'),
                'uid' => uniqid('NEW_'),
                'crop' => null,
            ]
        );
        $fileReference = GeneralUtility::makeInstance(FileReference::class);
        $fileReference->setOriginalResource($falFileReference);
        return $fileReference;
    }

    private function removeFileReference(\TYPO3\CMS\Extbase\Domain\Model\FileReference $ref)
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $qb->delete('sys_file_reference')
            ->where('uid = ' . $ref->getOriginalResource()->getUid())
            ->execute();
    }
}
