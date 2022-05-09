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

use SkillDisplay\Skills\Service\CertoBot;
use SkillDisplay\Skills\Service\Importer\ExportService;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Link extends AbstractEntity
{
    /**
     * title
     *
     * @var string
     * @TYPO3\CMS\Extbase\Annotation\Validate("NotEmpty")
     */
    protected $title = '';

    /**
     * url
     *
     * @var string
     * @TYPO3\CMS\Extbase\Annotation\Validate("NotEmpty")
     */
    protected $url = '';

    /** @var string */
    protected $color = '';

    /** @var int */
    protected $tstamp = 0;

    /** @var string */
    protected $uuid = '';

    /** @var string */
    protected $tablename = '';

    /** @var int */
    protected $imported = 0;

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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getExportJson(): string
    {
        $link = [
            'uuid' => $this->uuid,
            'type' => get_class($this),
            "uid" => $this->getUid(),

            'data' => [
                "tstamp" => $this->tstamp,
                "title" => $this->getTitle(),
                "url" => $this->getUrl(),
                "color" => $this->getColor(),
                "tablename" => $this->tablename,
            ]
        ];

        return json_encode($link);
    }

    public function getUUId(): string
    {
        return $this->uuid;
    }
}
