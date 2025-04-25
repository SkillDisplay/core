<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Service\Importer;

use RuntimeException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Link;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\Tag;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\LinkRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\TagRepository;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExportService extends AbstractImportExportService
{
    /** @var int[] */
    private array $brandIds = [];

    /** @var int[] */
    private array $tagIds = [];

    /** @var int[] */
    private array $linkIds = [];

    /** @var int[] */
    private array $skillIds = [];

    /** @var string[] */
    private array $lines = [];

    public function __construct(
        private readonly BrandRepository $brandRepository,
        private readonly LinkRepository $linkRepository,
        private readonly SkillPathRepository $skillSetRepository,
        private readonly TagRepository $tagRepository,
        private readonly SkillRepository $skillRepository
    ) {}

    public static function encodeFileReference(FileReference $file, array &$data, string $fieldName): bool
    {
        try {
            $imageData = file_get_contents($file->getForLocalProcessing(false));
            if ($imageData !== false) {
                $data['file-' . $fieldName] = base64_encode($imageData);
                $data['file-' . $fieldName . '-hash'] = $file->getSha1();
                $data['file-' . $fieldName . '-name'] = $file->getOriginalFile()->getName();
                return true;
            }
        } catch (RuntimeException) {
        }

        return false;
    }

    public static function getTranslations(string $table, int $parentUid, array $fields): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $results = $qb
            ->select('*')
            ->from($table)
            ->where($qb->expr()->eq('l10n_parent', $qb->createNamedParameter($parentUid, Connection::PARAM_INT)))
            ->andWhere($qb->expr()->gt('sys_language_uid', $qb->createNamedParameter(0, Connection::PARAM_INT)))
            ->executeQuery();

        $languageMapping = ExportService::getLanguageMapping();
        $translations = [];
        while ($row = $results->fetchAssociative()) {
            if (!isset($languageMapping[$row['sys_language_uid']])) {
                continue;
            }

            $entry = [
                'tstamp' => $row['tstamp'],
            ];
            foreach ($fields as $field) {
                $entry[$field] = $row[$field];
            }

            $translations[$languageMapping[$row['sys_language_uid']]] = $entry;
        }
        $results->free();

        return $translations;
    }

    /**
     * Exports the skillsets to the given path
     *
     * @param string $targetFileName
     * @param array $skillSetIds
     */
    public function doExport(string $targetFileName, array $skillSetIds): void
    {
        foreach ($skillSetIds as $skillSetId) {
            $this->collectSkillSet((int)$skillSetId);
        }

        $this->collectSkills();
        $this->collectTags();
        $this->collectLinks();
        $this->collectBrands();

        $this->writeResult($targetFileName);
    }

    private function collectSkillSet(int $skillSetId): void
    {
        /** @var ?SkillPath $skillSet */
        $skillSet = $this->skillSetRepository->findByUid($skillSetId);

        if (!$skillSet) {
            throw new RuntimeException('SkillSet with id ' . $skillSetId . ' cannot be found.', 3675348808);
        }

        $this->lines[] = $skillSet->getExportJson();

        foreach ($skillSet->getBrands() as $brand) {
            $this->brandIds[$brand->getUid()] = $brand->getUid();
        }

        foreach ($skillSet->getLinks() as $link) {
            $this->linkIds[$link->getUid()] = $link->getUid();
        }

        /** @var Skill $skill */
        foreach ($skillSet->getSkills() as $skill) {
            if (isset($this->skillIds[$skill->getUid()])) {
                continue;
            }
            $this->collectSkillDetails($skill);
            $this->skillIds[$skill->getUid()] = $skill->getUid();
            $preSkills = $skill->getPrerequisites(true);
            foreach ($preSkills as $preSkill) {
                if (isset($this->skillIds[$preSkill->getUid()])) {
                    continue;
                }
                $this->skillIds[$preSkill->getUid()] = $preSkill->getUid();
                $this->collectSkillDetails($preSkill);
            }
        }
    }

    private function collectSkillDetails(Skill $skill): void
    {
        foreach ($skill->getBrands() as $brand) {
            $this->brandIds[$brand->getUid()] = $brand->getUid();
        }

        if ($skill->getDomainTag()) {
            $this->tagIds[$skill->getDomainTag()->getUid()] = $skill->getDomainTag()->getUid();
        }

        /** @var Tag $tag */
        foreach ($skill->getTags() as $tag) {
            $this->tagIds[$tag->getUid()] = $tag->getUid();
        }

        /** @var Link $link */
        foreach ($skill->getLinks() as $link) {
            $this->linkIds[$link->getUid()] = $link->getUid();
        }
    }

    private function collectSkills(): void
    {
        foreach ($this->skillIds as $skillId) {
            /** @var ?Skill $skill */
            $skill = $this->skillRepository->findByUid($skillId);
            if (!$skill) {
                throw new RuntimeException('Referenced Skill with uid ' . $skillId . ' not found.', 1784046579);
            }
            $this->lines[] = $skill->getExportJson();
        }
    }

    private function collectTags(): void
    {
        foreach ($this->tagIds as $tagId) {
            /** @var ?Tag $tag */
            $tag = $this->tagRepository->findByUid($tagId);
            if (!$tag) {
                throw new RuntimeException('Referenced Tag with uid ' . $tagId . ' not found.', 3860297203);
            }
            $this->lines[] = $tag->getExportJson();
        }
    }

    private function collectLinks(): void
    {
        foreach ($this->linkIds as $linkId) {
            /** @var ?Link $link */
            $link = $this->linkRepository->findByUid($linkId);
            if (!$link) {
                throw new RuntimeException('Referenced Link with uid ' . $linkId . ' not found.', 9105813917);
            }
            $this->lines[] = $link->getExportJson();
        }
    }

    private function collectBrands(): void
    {
        foreach ($this->brandIds as $brandId) {
            /** @var ?Brand $brand */
            $brand = $this->brandRepository->findByUid($brandId);
            if (!$brand) {
                throw new RuntimeException('Referenced Brand with uid ' . $brandId . ' not found.', 9187916494);
            }
            $this->lines[] = $brand->getExportJson();
        }
    }

    private function writeResult(string $targetFileName): void
    {
        $file = fopen($targetFileName, 'w+');
        if ($file === false) {
            throw new RuntimeException($targetFileName . ' cannot be opened for export', 6050567354);
        }

        // generate placeholder header
        $this->lines[] = $this->emptyHeader();

        // calculate hash and write result to file
        $hash = '';
        for ($i = count($this->lines) - 1; $i >= 0; $i--) {
            $hash = $this->hash($hash . $this->lines[$i]);
            fwrite($file, $this->lines[$i] . PHP_EOL);
        }

        // replace header with real hash information
        rewind($file);
        fwrite($file, $this->fileHeader($hash));
        fclose($file);
    }
}
