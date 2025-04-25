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

use SkillDisplay\Skills\Service\CertoBot;
use SkillDisplay\Skills\Service\Importer\ExportService;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Tag extends AbstractEntity
{
    public const array TRANSLATE_FIELDS = [
        'title',
        'description',
    ];

    #[Validate(['validator' => 'NotEmpty'])]
    protected string $title = '';
    protected string $description = '';
    protected int $tstamp = 0;
    protected string $uuid = '';
    protected int $imported = 0;
    protected bool $domainTag = false;

    public function __construct()
    {
        $this->uuid = CertoBot::uuid();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getExportJson(): string
    {
        $tag = [
            'uuid' => $this->uuid,
            'type' => static::class,
            'uid' => $this->getUid(),
            'data' => [
                'tstamp' => $this->tstamp,
                'title' => $this->getTitle(),
                'description' => $this->getDescription(),
                'domainTag' => $this->domainTag,
                'translations' => ExportService::getTranslations('tx_skills_domain_model_tag', $this->getUid(), self::TRANSLATE_FIELDS),
            ],
        ];

        return json_encode($tag);
    }

    public function getUUId(): string
    {
        return $this->uuid;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isDomainTag(): bool
    {
        return $this->domainTag;
    }

    public function setDomainTag(bool $domainTag): void
    {
        $this->domainTag = $domainTag;
    }
}
