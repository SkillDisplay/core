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

use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Service\CertoBot;
use SkillDisplay\Skills\Service\Importer\ExportService;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\ORM\Lazy;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Domain\Repository\CategoryRepository;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyObjectStorage;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Service\ImageService;

class Brand extends AbstractEntity
{
    public const JsonViewConfiguration = [
        '_only' => [
            'uid', 'name', 'logoPublicUrl', 'url', 'memberCount', 'members', 'firstCategoryTitle',
        ],
        '_descend' => [
            'members' => [
                '_descendAll' => User::JsonUserViewConfiguration,
            ],
        ],
    ];

    public const JsonViewMinimalConfiguration = [
        '_only' => [
            'uid', 'name', 'logoPublicUrl', 'url', 'memberCount', 'firstCategoryTitle',
        ],
    ];

    public const TRANSLATE_FIELDS = [
        'name',
        'description',
        'url',
    ];

    /**
     * name
     *
     * @var string
     * @Validate("NotEmpty")
     */
    protected string $name = '';
    protected string $description = '';
    /** @var ObjectStorage<FileReference> */
    protected ObjectStorage $logo;
    protected ?FileReference $banner = null;
    protected ?FileReference $pixelLogo = null;
    protected string $url = '';

    /**
     * @var ObjectStorage<Category>
     * @Lazy
     */
    protected ObjectStorage|LazyObjectStorage $categories;
    protected int $partnerLevel = 0;
    protected int $patronageLevel = 0;

    /**
     * @var ObjectStorage<Brand>
     * @Lazy
     */
    protected ObjectStorage|LazyObjectStorage $patronages;
    protected bool $showNumOfCertificates = false;

    /**
     * @var ObjectStorage<User>
     * @Lazy
     */
    protected ObjectStorage|LazyObjectStorage $members;

    protected int $tstamp = 0;
    protected string $uuid = '';
    protected int $imported = 0;
    protected string $createdByBrand = '';
    protected bool $creditOverdraw = false;
    protected bool $billable = true;
    protected string $apiKey = '';
    protected string $billingAddress = '';
    protected string $country = '';
    protected string $vatId = '';
    protected string $foreignId = '';

    public function __construct()
    {
        $this->uuid = CertoBot::uuid();
        $this->logo = new ObjectStorage();
        $this->members = new ObjectStorage();
        $this->patronages = new ObjectStorage();
        $this->categories = new ObjectStorage();
    }

    public function getPatrons(): array
    {
        $brandRepo = GeneralUtility::makeInstance(BrandRepository::class);
        return $brandRepo->findPatronsForBrand($this);
    }

    public function getLogoPublicUrl(): string
    {
        /** @var FileReference $file */
        $file = $this->logo->toArray()[0] ?? null;
        return $file && $file->getOriginalResource() ? (string)$file->getOriginalResource()->getPublicUrl() : '';
    }

    public function getLogoForLocalProcessing(): string
    {
        /** @var FileReference $file */
        $file = $this->logo->toArray()[0] ?? null;
        return $file && $file->getOriginalResource() ? $file->getOriginalResource()->getForLocalProcessing(false) : '';
    }

    public function getBannerPublicUrl(): string
    {
        if (!$this->banner) {
            return '';
        }
        return (string)$this->getBannerScaled()->getPublicUrl();
    }

    public function getBannerScaled(): ?ProcessedFile
    {
        if (!$this->banner || !$this->banner->getOriginalResource()) {
            return null;
        }
        $imageService = GeneralUtility::makeInstance(ImageService::class);
        $processingInstructions = [
            'width' => '1100c',
            'height' => '220c',
        ];
        return $imageService->applyProcessingInstructions($this->banner->getOriginalResource(), $processingInstructions);
    }

    public function getLogoScaled(): ?ProcessedFile
    {
        $file = $this->logo->toArray()[0] ?? null;
        if (!$file || !$file->getOriginalResource()) {
            return null;
        }
        $imageService = GeneralUtility::makeInstance(ImageService::class);
        $processingInstructions = [
            'width' => '300c',
            'height' => '300c',
        ];
        return $imageService->applyProcessingInstructions($file->getOriginalResource(), $processingInstructions);
    }

    public function getPixelLogoPublicUrl(): string
    {
        if (!$this->pixelLogo || !$this->pixelLogo->getOriginalResource()) {
            return '';
        }
        return (string)$this->pixelLogo->getOriginalResource()->getPublicUrl();
    }

    public function getExportJson(): string
    {
        $data = [
            'tstamp' => $this->tstamp,
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'url' => $this->getUrl(),
        ];

        $data['translations'] = ExportService::getTranslations('tx_skills_domain_model_brand', $this->getUid(), self::TRANSLATE_FIELDS);

        $brand = [
            'uuid' => $this->uuid,
            'type' => get_class($this),
            'uid' => $this->getUid(),
            'data' => $data,
        ];

        /** @var FileReference $file */
        $file = $this->logo->toArray()[0] ?? null;
        if ($file && $file->getOriginalResource()) {
            ExportService::encodeFileReference($file->getOriginalResource(), $brand, 'logo');
        }

        if ($this->banner && $this->banner->getOriginalResource()) {
            ExportService::encodeFileReference($this->banner->getOriginalResource(), $brand, 'banner');
        }

        if ($this->pixelLogo && $this->pixelLogo->getOriginalResource()) {
            ExportService::encodeFileReference($this->pixelLogo->getOriginalResource(), $brand, 'pixel_logo');
        }

        return json_encode($brand);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Returns the logo
     *
     * @return ObjectStorage<FileReference>
     */
    public function getLogo(): ObjectStorage
    {
        return $this->logo;
    }

    /**
     * Returns the logo for mails
     *
     * @return FileReference|null
     */
    public function getPixelLogo(): ?FileReference
    {
        return $this->pixelLogo;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function addCategory(Category $category): void
    {
        $this->categories->attach($category);
    }

    public function removeCategory(Category $category): void
    {
        $this->categories->detach($category);
    }

    /**
     * @return ObjectStorage<Category>
     */
    public function getCategories(): ObjectStorage
    {
        return $this->categories;
    }

    public function setCategories(ObjectStorage $categories): void
    {
        $this->categories = $categories;
    }

    public function getFirstCategory(): ?Category
    {
        return $this->categories->toArray()[0] ?? null;
    }

    public function getFirstCategoryTitle(): string
    {
        if (!$this->getFirstCategory()) {
            return '';
        }
        $title = $this->getFirstCategory()->getTitle();
        // use original language title only (ignore translation here)
        if ($this->getFirstCategory()->_getProperty('_localizedUid') > 0) {
            $categoryRepo = GeneralUtility::makeInstance(CategoryRepository::class);
            $defaultCategory = $categoryRepo->findByUid($this->getFirstCategory()->getUid());
            $title = $defaultCategory->getTitle();
        }
        return $title;
    }

    public function getPartnerLevel(): int
    {
        return $this->partnerLevel;
    }

    public function setPartnerLevel(int $partnerLevel): void
    {
        $this->partnerLevel = $partnerLevel;
    }

    public function getPatronageLevel(): int
    {
        return $this->patronageLevel;
    }

    public function setPatronageLevel(int $patronageLevel): void
    {
        $this->patronageLevel = $patronageLevel;
    }

    public function getShowNumOfCertificates(): bool
    {
        return $this->showNumOfCertificates;
    }

    public function setShowNumOfCertificates(bool $showNumOfCertificates): void
    {
        $this->showNumOfCertificates = $showNumOfCertificates;
    }

    public function addPatronage(Brand $brand): void
    {
        $this->patronages->attach($brand);
    }

    public function removePatronage(Brand $brand): void
    {
        $this->patronages->detach($brand);
    }

    /**
     * @return ObjectStorage<Brand>
     */
    public function getPatronages(): ObjectStorage
    {
        return $this->patronages;
    }

    public function setPatronages(ObjectStorage $patronages): void
    {
        $this->patronages = $patronages;
    }

    /**
     * @return ObjectStorage<User>
     */
    public function getMembers(): ObjectStorage
    {
        return $this->members;
    }

    public function setMembers(ObjectStorage $members): void
    {
        $this->members = $members;
    }

    public function getUUId(): string
    {
        return $this->uuid;
    }

    public function getMemberCount(): int
    {
        return $this->members->count();
    }

    public function getCreditOverdraw(): bool
    {
        return $this->creditOverdraw;
    }

    public function setCreditOverdraw(bool $creditOverdraw): void
    {
        $this->creditOverdraw = $creditOverdraw;
    }

    public function getBillable(): bool
    {
        return $this->billable;
    }

    public function setBillable(bool $billable): void
    {
        $this->billable = $billable;
    }

    public function getCreatedByBrand(): string
    {
        return $this->createdByBrand;
    }

    public function setCreatedByBrand(string $createdByBrand): void
    {
        $this->createdByBrand = $createdByBrand;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function getBillingAddress(): string
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(string $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }

    /**
     * @return string
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * @param string $country
     */
    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    /**
     * @return string
     */
    public function getVatId(): string
    {
        return $this->vatId;
    }

    /**
     * @param string $vatId
     */
    public function setVatId(string $vatId): void
    {
        $this->vatId = $vatId;
    }

    public function getForeignId(): string
    {
        return $this->foreignId;
    }

    public function setForeignId(string $foreignId): void
    {
        $this->foreignId = $foreignId;
    }
}
