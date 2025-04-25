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

namespace SkillDisplay\Skills\Domain\Model;

use DateTime;
use JsonSerializable;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Annotation\ORM\Transient;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyObjectStorage;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Service\ImageService;

class User extends AbstractEntity implements JsonSerializable
{
    public const array JsonUserViewConfiguration = [
        '_only' => [
            'uid', 'firstName', 'lastName', 'email', 'userAvatar',
        ],
    ];

    public const array JsonViewConfiguration = [
        'managedOrganizations' => [
            '_descendAll' => Brand::JsonViewConfiguration,
        ],
    ];

    protected string $username = '';
    protected string $password = '';

    /**
     * @var ObjectStorage<FrontendUserGroup>
     */
    protected ObjectStorage $usergroup;

    protected string $name = '';
    protected string $firstName = '';
    protected string $middleName = '';
    protected string $lastName = '';
    protected string $address = '';
    protected string $telephone = '';
    protected string $fax = '';
    protected string $email = '';
    protected string $title = '';
    protected string $zip = '';
    protected string $city = '';
    protected string $country = '';
    protected string $www = '';
    protected string $company = '';

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $image;

    protected ?DateTime $lastlogin = null;

    #[Validate(['validator' => 'NotEmpty'])]
    protected bool $publishSkills = false;

    #[Transient]
    protected string $passwordRepeat = '';
    #[Transient]
    protected bool $terms = false;

    protected bool $disable = false;
    protected bool $newsletter = false;

    #[Cascade(['value' => 'remove'])]
    protected ?FileReference $avatar = null;

    /** @var ObjectStorage<Certifier> */
    protected ObjectStorage $favouriteCertifiers;

    /**
     * Brands the user is a manager for.
     *
     * @var ObjectStorage<Brand>|LazyObjectStorage
     */
    #[Lazy]
    protected ObjectStorage|LazyObjectStorage $managedBrands;

    /**
     * Brands the user is member of
     *
     * @var ObjectStorage<Brand>|LazyObjectStorage
     */
    #[Lazy]
    protected ObjectStorage|LazyObjectStorage $organisations;

    protected bool $mailPush = false;
    protected string $mailLanguage = 'en';
    protected string $linkedin = '';
    protected string $xing = '';
    protected string $github = '';
    protected string $twitter = '';
    protected string $pendingEmail = '';
    protected string $profileLink = '';

    protected ?DateTime $termsAccepted = null;
    protected ?DateTime $dataSync = null;
    protected bool $locked = false;
    protected bool $anonymous = false;
    protected string $monthlyActivity = '';
    protected string $foreignUsername = '';

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->favouriteCertifiers = new ObjectStorage();
        $this->managedBrands = new ObjectStorage();
        $this->organisations = new ObjectStorage();
        $this->usergroup = new ObjectStorage();
        $this->image = new ObjectStorage();
    }

    public function getPendingCertifications(): array
    {
        return GeneralUtility::makeInstance(CertificationRepository::class)->findPending($this);
    }

    public function getAcceptedCertifications(): array
    {
        return GeneralUtility::makeInstance(CertificationRepository::class)->findAccepted($this);
    }

    public function getDeclinedCertifications(): array
    {
        return GeneralUtility::makeInstance(CertificationRepository::class)->findDeclined($this);
    }

    public function getRevokedCertifications(): array
    {
        return GeneralUtility::makeInstance(CertificationRepository::class)->findRevoked($this);
    }

    public function hasRewardPrerequisite(RewardPrerequisite $prerequisite): bool
    {
        $certificationRepository = GeneralUtility::makeInstance(CertificationRepository::class);
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
     * @return array data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    #[\Override]
    public function jsonSerialize(): array
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
            'userAvatar' => $this->getUserAvatar(),
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
            'level' => $this->isLocked() ? 2 : 1,
            'organizations' => $this->getOrganisationsJsonData(),
            'managedOrganizations' => $this->getManagedBrands(),
            'company' => $this->company,
            'address' => $this->address,
            'city' => $this->city,
            'zipCode' => $this->zip,
            'country' => $this->country,
            'website' => $this->www,
            'twitter' => $this->twitter,
            'linkedin' => $this->linkedin,
            'xing' => $this->xing,
            'github' => $this->github,
            'publicAchievements' => $this->isPublishSkills(),
            'receiveEmails' => $this->isNewsletter(),
            'receiveNotifications' => $this->isMailPush(),
            'language' => $this->mailLanguage,
            'locked' => $this->locked,
        ];
    }

    public function toJsonBaseData(): array
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

    public function getAvatarRaw(): ?FileReference
    {
        return $this->avatar;
    }

    public function getAvatar()
    {
        if (!$this->avatar) {
            $placeholder = PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName('EXT:skills/Resources/Public/Images/anonymoususer1.png'));
            return GeneralUtility::makeInstance(ResourceFactory::class)->getFileObjectFromCombinedIdentifier($placeholder);
        }
        return $this->avatar;
    }

    public function getAvatarScaled(): ProcessedFile
    {
        $avatarFile = $this->getAvatar();
        $userImage = $avatarFile instanceof FileReference ? $avatarFile->getOriginalResource() : $avatarFile;
        $imageService = GeneralUtility::makeInstance(ImageService::class);
        $processingInstructions = [
            'width' => '300c',
            'height' => '300c',
        ];
        return $imageService->applyProcessingInstructions($userImage, $processingInstructions);
    }

    public function getUserAvatar(): string
    {
        return (string)$this->getAvatarScaled()->getPublicUrl();
    }

    public function getSkillUpStats(): array
    {
        $cacheIdentifier = 'userverifications_' . $this->uid;
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('skill_progress');
        $result = $cache->get($cacheIdentifier);
        if ($result) {
            return $result;
        }

        $cacheTags = [];
        $stats = [0, 0, 0, 0, 0];
        $certs = GeneralUtility::makeInstance(CertificationRepository::class)->findAcceptedForUser($this);
        /** @var Certification $cert */
        foreach ($certs as $cert) {
            $stats[$cert->getLevelNumber()]++;
            if ($cert->getSkill()) {
                $cacheTags[] = $cert->getSkill()->getCacheTag($this->uid);
            }
        }

        $cache->set($cacheIdentifier, $stats, $cacheTags);
        return $stats;
    }

    public function setAvatar(?FileReference $avatar = null): void
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

    public function setUsername($username): void
    {
        $this->username = $username;
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
     * @return ObjectStorage<Certifier>
     */
    public function getFavouriteCertifiers(): ObjectStorage
    {
        return $this->favouriteCertifiers;
    }

    /**
     * Sets the favouriteCertifiers
     *
     * @param ObjectStorage<Certifier> $favouriteCertifiers
     */
    public function setFavouriteCertifiers(ObjectStorage $favouriteCertifiers): void
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
     * @return ObjectStorage<Brand>
     */
    public function getManagedBrands(): ObjectStorage
    {
        return $this->managedBrands;
    }

    /**
     * Sets the managedBrands
     *
     * @param ObjectStorage<Brand> $managedBrands
     */
    public function setManagedBrands(ObjectStorage $managedBrands): void
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
     * @return ObjectStorage<Brand>
     */
    public function getOrganisations(): ObjectStorage
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
     * @param ObjectStorage<Brand> $orgas
     */
    public function setOrganisations(ObjectStorage $orgas): void
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

    public function getTermsAccepted(): ?DateTime
    {
        return $this->termsAccepted;
    }

    public function setTermsAccepted(?DateTime $termsAccepted): void
    {
        $this->termsAccepted = $termsAccepted;
    }

    public function isTermsAccepted(): bool
    {
        return $this->termsAccepted && $this->termsAccepted->getTimestamp() > 0;
    }

    public function isCertifier(): bool
    {
        /** @var CertifierRepository $repo */
        $repo = GeneralUtility::makeInstance(CertifierRepository::class);
        return $repo->count(['user' => $this]) > 0;
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

    public function getDataSync(): ?DateTime
    {
        return $this->dataSync;
    }

    public function setDataSync(?DateTime $dataSync): void
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
                'managers' => $managers,
            ];
        }
        return $data;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Sets the usergroups. Keep in mind that the property is called "usergroup"
     * although it can hold several usergroups.
     *
     * @param ObjectStorage<FrontendUserGroup> $usergroup
     */
    public function setUsergroup(ObjectStorage $usergroup): void
    {
        $this->usergroup = $usergroup;
    }

    public function addUsergroup(FrontendUserGroup $usergroup): void
    {
        $this->usergroup->attach($usergroup);
    }

    public function removeUsergroup(FrontendUserGroup $usergroup): void
    {
        $this->usergroup->detach($usergroup);
    }

    /**
     * Returns the usergroups. Keep in mind that the property is called "usergroup"
     * although it can hold several usergroups.
     *
     * @return ObjectStorage<FrontendUserGroup> An object storage containing the usergroup
     */
    public function getUsergroup(): ObjectStorage
    {
        return $this->usergroup;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setMiddleName(string $middleName): void
    {
        $this->middleName = $middleName;
    }

    public function getMiddleName(): string
    {
        return $this->middleName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setTelephone(string $telephone): void
    {
        $this->telephone = $telephone;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    public function setFax(string $fax): void
    {
        $this->fax = $fax;
    }

    public function getFax(): string
    {
        return $this->fax;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setZip(string $zip): void
    {
        $this->zip = $zip;
    }

    public function getZip(): string
    {
        return $this->zip;
    }

    public function setCity(string $city): void
    {
        $this->city = $city;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setWww(string $www): void
    {
        $this->www = $www;
    }

    public function getWww(): string
    {
        return $this->www;
    }

    public function setCompany(string $company): void
    {
        $this->company = $company;
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    /**
     * Sets the image value
     *
     * @param ObjectStorage<FileReference> $image
     */
    public function setImage(ObjectStorage $image): void
    {
        $this->image = $image;
    }

    /**
     * Gets the image value
     *
     * @return ObjectStorage<FileReference>
     */
    public function getImage(): ObjectStorage
    {
        return $this->image;
    }

    public function setLastlogin(DateTime $lastlogin): void
    {
        $this->lastlogin = $lastlogin;
    }

    public function getLastlogin(): ?DateTime
    {
        return $this->lastlogin;
    }
}
