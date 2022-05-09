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

namespace SkillDisplay\Skills\Domain\Model;

use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUserGroup;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Core\Utility\PathUtility;

class User extends FrontendUser implements \JsonSerializable
{
    const JsonUserViewConfiguration = [
        '_only' => [
            'uid', 'firstName', 'lastName', 'email', 'userAvatar'
        ]
    ];

    const JsonViewConfiguration = [
        'managedOrganizations' => [
            '_descendAll' => Brand::JsonViewConfiguration,
        ],
    ];

    /** @var bool */
    protected $disable = false;

    /**
     * publishSkills
     *
     * @var bool
     * @TYPO3\CMS\Extbase\Annotation\Validate("NotEmpty")
     */
    protected $publishSkills = false;

    /**
     * virtual not stored
     *
     * @var string
     */
    protected $passwordRepeat = '';

    /** @var bool */
    protected $newsletter = false;

    /**
     * avatar
     *
     * @var \TYPO3\CMS\Extbase\Domain\Model\FileReference
     * @TYPO3\CMS\Extbase\Annotation\ORM\Cascade("remove")
     */
    protected $avatar = null;

    /** @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Certifier> */
    protected $favouriteCertifiers = null;

    /**
     * Brands the user is a manager for.
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Brand>
     * @TYPO3\CMS\Extbase\Annotation\ORM\Lazy
     */
    protected $managedBrands = null;

    /**
     * Brands the user is member of
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Brand>
     * @TYPO3\CMS\Extbase\Annotation\ORM\Lazy
     */
    protected $organisations = null;

    /** @var bool */
    protected $mailPush = false;

    /** @var string */
    protected $mailLanguage = 'en';

    /**
     * virtual property
     *
     * @var bool
     */
    protected $terms = false;

    /** @var string */
    protected $linkedin = '';

    /** @var string */
    protected $xing = '';

    /** @var string */
    protected $github = '';

    /** @var string */
    protected $twitter = '';

    /** @var string */
    protected $pendingEmail = '';

    /** @var string */
    protected $profileLink = '';

    /** @var \DateTime|null */
    protected $termsAccepted;

    /** @var \DateTime|null */
    protected $dataSync;

    /** @var int */
    protected $adminGroupId = 0;

    /** @var bool */
    protected $locked = false;

    /** @var bool */
    protected $anonymous = false;

    /** @var string */
    protected $monthlyActivity = '';

    /** @var string */
    protected $foreignUsername = '';

    public function __construct()
    {
        parent::__construct();
        $this->favouriteCertifiers = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $this->managedBrands = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $this->organisations = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
    }

    public function getPendingCertifications(): array
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get(CertificationRepository::class)->findPending($this);
    }

    public function getAcceptedCertifications(): array
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get(CertificationRepository::class)->findAccepted($this);
    }

    public function getDeclinedCertifications(): array
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get(CertificationRepository::class)->findDeclined($this);
    }

    public function getRevokedCertifications(): array
    {
        return GeneralUtility::makeInstance(ObjectManager::class)->get(CertificationRepository::class)->findRevoked($this);
    }

    public function hasRewardPrerequisite(RewardPrerequisite $prerequisite) : bool
    {
        $certificationRepository = GeneralUtility::makeInstance(ObjectManager::class)->get(CertificationRepository::class);
        $userCerts = $certificationRepository->findBySkillsAndUser([$prerequisite->getSkill()], $this, false);
        $hasPrerequisites = false;
        foreach ($userCerts as $reachedCert) {
            if ($reachedCert->isValid()
                && $reachedCert->getLevelNumber() === $prerequisite->getLevel()
                && (!$prerequisite->getBrand() || !$reachedCert->getBrand() || $reachedCert->getBrand()->getUid() === $prerequisite->getBrand()->getUid())
            ) {
                $hasPrerequisites = true;
                break;
            }
        }
        return $hasPrerequisites;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return $this->toJsonData();
    }

    public function toMinimalJsonData(): array
    {
        return [
            'uid' => $this->uid,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'userAvatar' => $this->getUserAvatar()
        ];
    }

    public function toJsonData(): array
    {
        return [
            'uid' => $this->uid,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'userAvatar' => $this->getUserAvatar(),
            'isVerifier' => $this->isCertifier(),
            'isVerified' => $this->isLocked(),
            'level'=> $this->isLocked() ? 2 : 1,
            'organizations' => $this->getOrganisationsJsonData(),
            'managedOrganizations' => $this->getManagedBrands(),
            'company'=> $this->company,
            'address'=> $this->address,
            'city'=> $this->city,
            'zipCode'=> $this->zip,
            'country'=> $this->country,
            'website'=> $this->www,
            'twitter'=> $this->twitter,
            'linkedin'=> $this->linkedin,
            'xing'=> $this->xing,
            'github'=> $this->github,
            'publicAchievements'=> $this->isPublishSkills(),
            'receiveEmails'=> $this->isNewsletter(),
            'receiveNotifications'=> $this->isMailPush(),
            'language'=> $this->mailLanguage,
            'locked' => $this->locked
        ];
    }

    public function toJsonBaseData() : array
    {
        $userData = [];
        $userData['uid'] = $this->getUid();
        $userData['anonymous'] = $this->anonymous;
        $userData['firstName'] = $this->getFirstName();
        $userData['lastName'] = $this->getLastName();
        $userData['email'] = $this->getEmail();
        $userData['isVerifier'] = $this->isCertifier();
        $userData['showSyncBanner'] = $this->getForeignUsername() !== '' && !$this->hasDataSynced();
        $userData['userAvatar'] = (string)$this->getAvatarScaled()->getPublicUrl();
        $acceptedCertifications = $this->getSkillUpStats();
        $userData['skillPointData'] = [
            'self' => $acceptedCertifications[3],
            'education' => $acceptedCertifications[2],
            'business' => $acceptedCertifications[4],
            'certificate' => $acceptedCertifications[1],
        ];
        return $userData;
    }

    public function getPublishSkills(): bool
    {
        return $this->publishSkills;
    }

    public function isPublishSkills(): bool
    {
        return $this->publishSkills;
    }

    public function setPublishSkills(bool $publishSkills): void
    {
        $this->publishSkills = $publishSkills;
    }

    public function getAvatarRaw(): ?\TYPO3\CMS\Extbase\Domain\Model\FileReference
    {
        return $this->avatar;
    }

    /**
     * Returns the avatar
     *
     * @return \TYPO3\CMS\Extbase\Domain\Model\FileReference|File
     */
    public function getAvatar()
    {
        if (!$this->avatar || !$this->avatar->getOriginalResource()) {
            $placeholder = PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName('EXT:skills/Resources/Public/Images/anonymoususer.png'));
            return GeneralUtility::makeInstance(ResourceFactory::class)->getFileObjectFromCombinedIdentifier($placeholder);
        }
        return $this->avatar;
    }

    public function getAvatarScaled(): ProcessedFile
    {
        $avatarFile = $this->getAvatar();
        $userImage = $avatarFile instanceof \TYPO3\CMS\Extbase\Domain\Model\FileReference ? $avatarFile->getOriginalResource() : $avatarFile;
        $imageService = GeneralUtility::makeInstance(ObjectManager::class)->get(ImageService::class);
        $processingInstructions = [
            'width' => '300c',
            'height' => '300c',
        ];
        return $imageService->applyProcessingInstructions($userImage, $processingInstructions);
    }

    public function getUserAvatar() : string
    {
        return (string)$this->getAvatarScaled()->getPublicUrl();
    }

    public function getSkillUpStats() : array
    {
        $stats = [0,0,0,0,0];
        $certs = GeneralUtility::makeInstance(ObjectManager::class)->get(CertificationRepository::class)->findAcceptedForUser($this);
        /** @var Certification $cert */
        foreach ($certs as $cert) {
            $stats[$cert->getLevelNumber()]++;
        }
        return $stats;
    }

    public function setAvatar(\TYPO3\CMS\Extbase\Domain\Model\FileReference $avatar = null): void
    {
        $this->avatar = $avatar;
    }

    public function isDisabled(): bool
    {
        return $this->disable;
    }

    public function setDisable(bool $disable): void
    {
        $this->disable = $disable;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        parent::setUsername($username);
        $this->setEmail($username);
    }

    public function addFavouriteCertifier(Certifier $favouriteCertifier): void
    {
        $this->favouriteCertifiers->attach($favouriteCertifier);
    }

    public function removeFavouriteCertifier(Certifier $favouriteCertifierToRemove): void
    {
        $this->favouriteCertifiers->detach($favouriteCertifierToRemove);
    }

    /**
     * Returns the favouriteCertifiers
     *
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Certifier>
     */
    public function getFavouriteCertifiers()
    {
        return $this->favouriteCertifiers;
    }

    /**
     * Sets the favouriteCertifiers
     *
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Certifier> $favouriteCertifiers
     * @return void
     */
    public function setFavouriteCertifiers(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $favouriteCertifiers): void
    {
        $this->favouriteCertifiers = $favouriteCertifiers;
    }

    public function addManagedBrand(Brand $managedBrand): void
    {
        $this->managedBrands->attach($managedBrand);
    }

    public function removeManagedBrand(Brand $managedBrandToRemove): void
    {
        $this->managedBrands->detach($managedBrandToRemove);
    }

    /**
     * Returns the managedBrands
     *
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Brand>
     */
    public function getManagedBrands()
    {
        return $this->managedBrands;
    }

    /**
     * Sets the managedBrands
     *
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Brand> $managedBrands
     * @return void
     */
    public function setManagedBrands(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $managedBrands): void
    {
        $this->managedBrands = $managedBrands;
    }

    public function addOrganisation(Brand $orga): void
    {
        $this->organisations->attach($orga);
    }

    public function removeOrganisation(Brand $orga): void
    {
        $this->organisations->detach($orga);
    }

    /**
     * Returns the managedBrands
     *
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Brand>
     */
    public function getOrganisations()
    {
        return $this->organisations;
    }

    public function getOrganisationIds(): array
    {
        $idList = [];
        foreach ($this->organisations as $organisation) {
            $idList[] = $organisation->getUid();
        }

        return $idList;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Brand> $orgas
     * @return void
     */
    public function setOrganisations(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $orgas): void
    {
        $this->organisations = $orgas;
    }

    public function isMailPush(): bool
    {
        return $this->mailPush;
    }

    public function setMailPush(bool $mailPush): void
    {
        $this->mailPush = $mailPush;
    }

    public function getMailLanguage(): string
    {
        return $this->mailLanguage;
    }

    public function setMailLanguage(string $mailLanguage): void
    {
        $this->mailLanguage = $mailLanguage;
    }

    public function getPasswordRepeat(): string
    {
        return $this->passwordRepeat;
    }

    public function setPasswordRepeat(string $passwordRepeat): void
    {
        $this->passwordRepeat = $passwordRepeat;
    }

    public function isTerms(): bool
    {
        return $this->terms;
    }

    public function setTerms(bool $terms): void
    {
        $this->terms = $terms;
    }

    public function isNewsletter(): bool
    {
        return $this->newsletter;
    }

    public function setNewsletter(bool $newsletter): void
    {
        $this->newsletter = $newsletter;
    }

    public function getLinkedin(): string
    {
        return $this->linkedin;
    }

    public function setLinkedin(string $linkedin): void
    {
        $this->linkedin = $linkedin;
    }

    public function getXing(): string
    {
        return $this->xing;
    }

    public function setXing(string $xing): void
    {
        $this->xing = $xing;
    }

    public function getGithub(): string
    {
        return $this->github;
    }

    public function setGithub(string $github): void
    {
        $this->github = $github;
    }

    public function getTwitter(): string
    {
        return $this->twitter;
    }

    public function setTwitter(string $twitter): void
    {
        $this->twitter = $twitter;
    }

    public function getPendingEmail(): string
    {
        return $this->pendingEmail;
    }

    public function setPendingEmail(string $pendingEmail): void
    {
        $this->pendingEmail = $pendingEmail;
    }

    public function getProfileLink(): string
    {
        return $this->profileLink;
    }

    public function setProfileLink(string $profileLink): void
    {
        $this->profileLink = $profileLink;
    }

    public function getTermsAccepted(): ?\DateTime
    {
        return $this->termsAccepted;
    }

    public function setTermsAccepted(?\DateTime $termsAccepted): void
    {
        $this->termsAccepted = $termsAccepted;
    }

    public function isTermsAccepted() : bool
    {
        return $this->termsAccepted && $this->termsAccepted->getTimestamp() > 0;
    }

    public function isCertifier() : bool
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var QueryInterface $certifiers */
        $certifiers = $objectManager->get(CertifierRepository::class)->findByUser($this);
        $count = $certifiers->count();
        return $count > 0;
    }

    public function isAdmin(): bool
    {
        if (!$this->adminGroupId) {
            return false;
        }
        /** @var FrontendUserGroup $usergroup */
        foreach ($this->usergroup as $usergroup) {
            if ($usergroup->getUid() === $this->adminGroupId) {
                return true;
            }
        }
        return false;
    }

    public function getAdminGroupId(): int
    {
        return $this->adminGroupId;
    }

    public function setAdminGroupId(int $adminGroupId): void
    {
        $this->adminGroupId = $adminGroupId;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }

    public function isAnonymous(): bool
    {
        return $this->anonymous;
    }

    public function setAnonymous(bool $anonymous): void
    {
        $this->anonymous = $anonymous;
    }

    public function getForeignUsername(): string
    {
        return $this->foreignUsername;
    }

    public function setForeignUsername(string $foreignUsername): void
    {
        $this->foreignUsername = $foreignUsername;
    }

    public function getDataSync(): ?\DateTime
    {
        return $this->dataSync;
    }

    public function setDataSync(?\DateTime $dataSync): void
    {
        $this->dataSync = $dataSync;
    }

    private function hasDataSynced(): bool
    {
        return $this->dataSync && $this->dataSync->getTimestamp() > 0;
    }

    public function getMonthlyActivity(): array
    {
        $data = json_decode($this->monthlyActivity, true);
        if (is_array($data)) {
            $firstMonth = key($data);
            $data = array_values($data); // strip keys to make sure that this is also an array in frontend
            array_unshift($data, $firstMonth);
            return $data;
        }

        return [];
    }

    public function setMonthlyActivity(string $monthlyActivity): void
    {
        $this->monthlyActivity = $monthlyActivity;
    }

    private function getOrganisationsJsonData(): array
    {

        $data = [];
        /** @var UserRepository $userRepository */
        $userRepository = GeneralUtility::makeInstance(UserRepository::class);
        /** @var Brand $organisation */
        foreach ($this->organisations as $organisation) {
            $managers = [];
            /** @var User $manager */
            foreach ($userRepository->findManagers($organisation) as $manager) {
                $managers[] = $manager->toMinimalJsonData();
            }
            $data[] = [
                'uid' => $organisation->getUid(),
                'name' => $organisation->getName(),
                'logoPublicUrl' => $organisation->getLogoPublicUrl(),
                'url' => $organisation->getUrl(),
                'memberCount' => $organisation->getMemberCount(),
                'firstCategoryTitle' => $organisation->getFirstCategoryTitle(),
                'managers' => $managers
            ];
        }
        return $data;
    }
}
