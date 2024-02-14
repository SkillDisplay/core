<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Service\Importer;

use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Logging\SQLLogger;
use InvalidArgumentException;
use RuntimeException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Link;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\Tag;
use SkillDisplay\Skills\Hook\ImportDataHandler;
use SkillDisplay\Skills\Service\TranslatedUuidService;
use Symfony\Component\Console\Style\StyleInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFileNameException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Service\ClearCacheService;

class ImportService extends AbstractImportExportService implements SQLLogger
{
    public const RESOLVE_ASK = 0;
    public const RESOLVE_FORCE = 1;
    public const RESOLVE_IGNORE = 2;

    private const importLogFileName = 'import_%s.log';

    private const logSqlActive = false;

    private const tableMapping = [
        Brand::class => 'tx_skills_domain_model_brand',
        Link::class => 'tx_skills_domain_model_link',
        Tag::class => 'tx_skills_domain_model_tag',
        Skill::class => 'tx_skills_domain_model_skill',
        SkillPath::class => 'tx_skills_domain_model_skillpath',
    ];

    private const labelFieldMapping = [
        'tx_skills_domain_model_brand' => 'name',
        'tx_skills_domain_model_link' => 'title',
        'tx_skills_domain_model_tag' => 'title',
        'tx_skills_domain_model_skill' => 'title',
        'tx_skills_domain_model_skillpath' => 'name',
    ];

    /** @var resource */
    private $logFile;

    private StyleInterface $output;

    private int $pid = 0;

    private ResourceStorage $storage;

    private int $importTimeStamp = 0;

    private array $requirements = [];

    private int $resolveMode = 0;

    private int $currentLineNumber = 0;

    private int $refIndex = 0;

    public function __construct(StyleInterface $output)
    {
        $this->output = $output;
    }

    public function doImport(string $sourceFileName, int $targetStorageId, int $targetPid, int $resolveMode): void
    {
        $this->logFile = $this->openLog(self::importLogFileName, $sourceFileName);

        try {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName('Default');
        } catch (DBALException $exception) {
            $this->log($exception->getMessage());
            $connection = null;
        }
        if ($connection) {
            $connection->getConfiguration()->setSQLLogger($this);
            $connection->beginTransaction();
        }

        $this->pid = $targetPid;
        $this->importTimeStamp = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        $this->resolveMode = $resolveMode;

        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $this->storage = $resourceFactory->getStorageObject($targetStorageId);

        $stdin = $this->openStdIn();
        if (!$stdin) {
            $this->log('Failed to open STDIN for reading!');
            return;
        }
        $sourceFile = $this->openSourceFile($sourceFileName);
        if (!$sourceFile) {
            $this->log("Failed to open source file '$sourceFileName' for reading!");
            return;
        }

        if (!$this->validateFileIntegrity($sourceFile)) {
            $this->log('Source file is invalid. Hash validation failed.');
            return;
        }
        $this->log('Importing from ' . $sourceFileName);
        try {
            try {
                $this->importLines($sourceFile);
            } catch (RuntimeException $runtimeException) {
                $this->log('Error during import: ' . $runtimeException->getMessage());

                if ($connection) {
                    $this->log('Rollback of changes');
                    $connection->rollBack();
                }
                return;
            }

            if ($connection) {
                $connection->commit();
            }
        } catch (ConnectionException $connectionException) {
            $this->log($connectionException->getMessage());
        }

        $coreCacheService = GeneralUtility::makeInstance(ClearCacheService::class);
        $coreCacheService->clearAll();
    }

    public function validate(string $sourceFileName): bool
    {
        $sourceFile = $this->openSourceFile($sourceFileName);
        if (!$sourceFile) {
            return false;
        }
        return $this->validateFileIntegrity($sourceFile);
    }

    private function importLines($sourceFile): void
    {
        // Disable localization for DataHandler runs, we take care manually
        foreach (self::tableMapping as $table) {
            unset($GLOBALS['TCA'][$table]['ctrl']['languageField']);
        }

        $languages = array_flip(self::getLanguageMapping());

        rewind($sourceFile);
        // read first line (header) blindly
        fgets($sourceFile);
        $this->currentLineNumber = 1;
        while (($line = fgets($sourceFile)) !== false) {
            $this->currentLineNumber++;
            $data = json_decode($line, true);

            if (!isset(self::tableMapping[$data['type']])) {
                $this->log('table mapping for ' . $data['type'] . ' is not defined');
                continue;
            }

            if ($data['type'] === Skill::class && isset($data['domain_tag'])) {
                $data['data']['domain_tag'] = $this->findByUUid('tx_skills_domain_model_tag', $data['domain_tag'])['uid'];
            }
            $uid = $this->importRow(self::tableMapping[$data['type']], $data['uuid'], $data['data'], 0, 0);

            foreach ($data as $key => $value) {
                $fileParts = explode('-', $key);
                if (count($fileParts) === 2 && $fileParts[0] === 'file') {
                    $this->importFile($uid, self::tableMapping[$data['type']], $data, $key);
                }
            }

            if (isset($data['data']['translations'])) {
                $this->log('Importing translations');
                foreach ($data['data']['translations'] as $key => $translation) {
                    if (isset($languages[$key])) {
                        $this->importRow(self::tableMapping[$data['type']], TranslatedUuidService::getTranslatedUuid($data['uuid'], $key), $translation, $languages[$key], $uid);
                    } else {
                        $this->log('language with iso code ' . $key . ' not configured, skipping');
                    }
                }
            }

            if ($data['type'] === Skill::class) {
                $this->importSkillRelations($uid, $data);
            } elseif ($data['type'] === SkillPath::class) {
                $this->importSkillSetRelations($uid, $data);
            }
        }

        $this->log('Persisting skill requirements');
        foreach ($this->requirements as $requirement) {
            $this->generateSkillRequirements($requirement['uid'], $requirement['requirements']);
        }
    }

    private function findByUUid(string $table, string $uuid, bool $reportMissing = true): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $rows = $qb->select('*')
                      ->from($table)
                      ->where(
                          $qb->expr()->eq('uuid', $qb->createNamedParameter($uuid))
                      )
                      ->executeQuery()->fetchAllAssociative();
        $count = count($rows);
        if ($count === 1) {
            return $rows[0];
        }
        if ($count > 1) {
            $this->log($table . ' with uuid ' . $uuid . ' not unique (count: ' . $count . ', example: ' . $rows[0][self::labelFieldMapping[$table]] . ')');
            throw new RuntimeException('Too many entities found');
        }
        if ($reportMissing) {
            $this->log($table . ' with uuid ' . $uuid . ' not found');
            throw new RuntimeException('Entity not found');
        }
        return [];
    }

    private function importRow(string $table, string $uuid, array $entry, int $langUid, int $parent): int
    {
        $row = $this->findByUUid($table, $uuid, false);
        if (!$row) {
            return $this->createNew($table, $uuid, $entry, $langUid, $parent);
        }
        return $this->updateExisting($row, $table, $uuid, $entry);
    }

    private function updateExisting(array $row, string $table, string $uuid, array $entry): int
    {
        $changedFieldNames = [];
        foreach ($this->filterData($entry) as $key => $value) {
            if (trim((string)$row[$key]) !== trim((string)$value)) {
                $changedFieldNames[] = $key;
            }
        }

        if (empty($changedFieldNames)) {
            return $row['uid'];
        }

        $modifiedLocal = (int)$row['tstamp'];
        $importedLocal = (int)$row['imported'];
        $modifiedExport = (int)$entry['tstamp'];

        $doUpdate = false;
        if ($modifiedLocal === $importedLocal) {
            // no local modification was done since last import
            $doUpdate = true;
        } else {
            switch ($this->resolveMode) {
                case ImportService::RESOLVE_FORCE:
                    $doUpdate = true;
                    break;
                case ImportService::RESOLVE_IGNORE:
                    break;
                case ImportService::RESOLVE_ASK:
                    $changes = [
                        ['tstamp', date('Y-m-d H:i:s', $modifiedLocal), date('Y-m-d H:i:s', $modifiedExport)],
                    ];
                    foreach ($changedFieldNames as $key) {
                        $changes[] = [$key, $row[$key], $entry[$key]];
                    }
                    $this->log('Difference found in table ' . $table . ':' . $uuid . ' (' . $row[self::labelFieldMapping[$table]] . ')');
                    $this->logTable($changes, ['Field', 'Local Value', 'Import Value']);

                    $doUpdate = $this->output->confirm('Overwrite local values?', false);
                    break;
            }
        }

        if ($doUpdate) {
            $this->log('updating table ' . $table . ':' . $uuid . ' (' . $row[self::labelFieldMapping[$table]] . ')');
            $this->updateRow($table, $row['uid'], $entry);
        } else {
            $this->log('skipping table ' . $table . ':' . $uuid . ' (' . $row[self::labelFieldMapping[$table]] . ')');
        }
        return $row['uid'];
    }

    private function createNew(string $table, string $uuid, array $data, int $langUid, int $parent): int
    {
        $insertData = [];
        $key = 'NEW1';
        $insertData[$table][$key] = $this->filterData($data);
        $insertData[$table][$key]['pid'] = $this->pid;
        $insertData[$table][$key]['imported'] = $this->importTimeStamp;
        $insertData[$table][$key]['uuid'] = $uuid;

        if ($parent > 0 && $langUid > 0) {
            $insertData[$table][$key]['sys_language_uid'] = $langUid;
            $insertData[$table][$key]['l10n_parent'] = $parent;
        }

        $this->log('creating new record table ' . $table . ':' . $uuid . ' (' . $data[self::labelFieldMapping[$table]] . ')');
        $dataHandler = $this->getDataHandler($insertData, []);
        $dataHandler->process_datamap();

        if (empty($dataHandler->substNEWwithIDs[$key])) {
            throw new RuntimeException('insert failed with data: ' . print_r($insertData, true), 1594111148);
        }

        return $dataHandler->substNEWwithIDs[$key];
    }

    private function filterData(array $data): array
    {
        return array_diff_key($data, ['tstamp' => 0, 'translations' => 0]);
    }

    private function updateRow(string $table, int $uid, array $data): void
    {
        $insertData = [];
        $key = $uid;
        $insertData[$table][$key] = $this->filterData($data);
        $insertData[$table][$key]['imported'] = $this->importTimeStamp;

        $this->getDataHandler($insertData, [])->process_datamap();
    }

    private function importFile(int $uid, string $table, array $data, string $field): void
    {
        $fields = explode('-', $field);
        if (count($fields) !== 2) {
            $this->log('field ' . $field . ' has invalid format');
            return;
        }

        $existingFile = $this->fetchFile($data[$field . '-hash']);
        if ($existingFile === 0) {
            $existingFile = $this->addFile($uid, $table, $data, $field);
        } elseif ($this->fileReferenceExists($existingFile, $table, $uid, $fields[1])) {
            return;
        }

        if ($existingFile) {
            $this->refIndex += 1;
            $key = 'NEWREF' . $this->refIndex;
            $insertData = [];
            $insertData[$table][$uid] = [$fields[1] => $key];
            $insertData['sys_file_reference'][$key] = ['uid_local' => $existingFile, 'pid' => $this->pid];
            $this->getDataHandler($insertData, [])->process_datamap();
        }
    }

    private function fetchFile(string $hash): int
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
        $result = $qb->select('uid')
                      ->from('sys_file')
                      ->where(
                          $qb->expr()->eq('sha1', $qb->createNamedParameter($hash))
                      )
                      ->executeQuery()->fetchAssociative();

        return $result['uid'] ?? 0;
    }

    private function addFile(int $uid, string $table, array $data, string $field): int
    {
        $folder = $this->getFolder(['imported']);
        $tmpFile = '/tmp/' . $data[$field . '-name'];

        if (file_put_contents($tmpFile, base64_decode($data[$field]))) {
            try {
                /** @var File $file */
                $file = $this->storage->addFile(
                    $tmpFile,
                    $folder,
                    $data[$field . '-name'],
                    DuplicationBehavior::REPLACE,
                );
            } catch (InvalidArgumentException|ExistingTargetFileNameException $ex) {
                $this->log('File ' . $tmpFile . ' exception: ' . $ex->getMessage());
            }
            $this->cleanupFileReferences($table, $uid);
            if ($file) {
                return $file->getUid();
            }
        }

        $this->log('Cannot add file ' . $data[$field . '-name'] . ' for ' . $table . ':' . $uid);
        return 0;
    }

    /**
     * Gives the correct folder at the specified path, creates folders if they do not exist
     *
     * @param array $path
     * @return Folder|null
     */
    private function getFolder(array $path): ?Folder
    {
        $folder = null;
        try {
            $folder = $this->storage->getRootLevelFolder();

            foreach ($path as $item) {
                if (!$folder->hasFolder($item)) {
                    $folder = $folder->createFolder($item);
                } else {
                    $folder = $folder->getSubfolder($item);
                }
            }
        } catch (InvalidArgumentException $ex) {
            $this->log('File cannot create folders' . $ex->getMessage());
        }

        return $folder;
    }

    private function cleanupFileReferences(string $table, int $uid): void
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $qb->delete('sys_file_reference')
           ->where(
               $qb->expr()->eq('tablenames', $qb->createNamedParameter($table))
           )
           ->andWhere(
               $qb->expr()->eq('uid_foreign', $qb->createNamedParameter($uid, Connection::PARAM_INT))
           )
           ->executeStatement();
    }

    private function fileReferenceExists(int $fileUid, string $table, int $uid, string $field): bool
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file_reference');
        $numberOfReferences = $qb
            ->count('*')
            ->from('sys_file_reference')
            ->where(
                $qb->expr()->eq('tablenames', $qb->createNamedParameter($table)),
                $qb->expr()->eq('fieldname', $qb->createNamedParameter($field)),
                $qb->expr()->eq('uid_local', $qb->createNamedParameter($fileUid, Connection::PARAM_INT)),
                $qb->expr()->eq('uid_foreign', $qb->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();

        return $numberOfReferences === 1;
    }

    private function importSkillRelations(int $uid, array $skill): void
    {
        $this->log('Importing brand relations');
        foreach ($skill['brands'] as $brand) {
            $this->insertSkillBrandRelation($uid, $brand);
        }

        $this->log('Importing link relations');
        foreach ($skill['links'] as $link) {
            $this->setLinkForSkill($uid, $link);
        }

        $this->log('Importing tag relations');
        foreach ($skill['tags'] as $tag) {
            $this->insertSkillTagRelation($uid, $tag);
        }

        $this->requirements[] = ['uid' => $uid, 'requirements' => $skill['requirements']];
    }

    private function insertSkillBrandRelation(int $uid, string $uuidBrand): void
    {
        $brand = $this->findByUUid('tx_skills_domain_model_brand', $uuidBrand);
        $this->insertMMTable('tx_skills_skill_brand_mm', $uid, $brand['uid']);
    }

    private function insertMMTable(string $table, int $uidLocal, int $uidForeign): void
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);

        $result = $qb
            ->select('uid_local')
            ->from($table)
            ->where(
                $qb->expr()->eq('uid_local', $qb->createNamedParameter($uidLocal, Connection::PARAM_INT))
            )
            ->andWhere(
                $qb->expr()->eq('uid_foreign', $qb->createNamedParameter($uidForeign, Connection::PARAM_INT))
            )
            ->executeQuery()->fetchAllAssociative();

        if (!empty($result)) {
            return;
        }

        $qb
            ->insert($table)
            ->values([
                'uid_local' => $uidLocal,
                'uid_foreign' => $uidForeign,
            ])
            ->executeStatement();
    }

    private function setLinkForSkill(int $uid, string $uuidLink): void
    {
        $this->setLinkRelation($uid, 'tx_skills_domain_model_skill', $uuidLink);
    }

    private function setLinkRelation(int $id, string $tablename, string $uuidLink): void
    {
        $table = 'tx_skills_domain_model_link';
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $qb->update($table)
           ->where(
               $qb->expr()->eq('uuid', $qb->createNamedParameter($uuidLink))
           )
           ->set('tablename', $tablename)
           ->set('skill', $id)
           ->executeStatement();
    }

    private function insertSkillTagRelation(int $uid, string $uuidTag): void
    {
        $tag = $this->findByUUid('tx_skills_domain_model_tag', $uuidTag);
        $this->insertMMTable('tx_skills_skill_tag_mm', $uid, $tag['uid']);
    }

    private function importSkillSetRelations(int $uid, array $skillSet): void
    {
        $this->log('Importing brand relations');
        foreach ($skillSet['brands'] as $brand) {
            $this->insertSkillSetBrandRelation($uid, $brand);
        }

        $this->log('Importing link relations');
        foreach ($skillSet['links'] as $link) {
            $this->setLinkForSkillSet($uid, $link);
        }

        $this->log('Importing skill relations');
        foreach ($skillSet['skills'] as $skill) {
            $this->insertSkillSetSkillRelation($uid, $skill);
        }
    }

    private function insertSkillSetBrandRelation(int $uid, string $uuidBrand): void
    {
        $brand = $this->findByUUid('tx_skills_domain_model_brand', $uuidBrand);
        $this->insertMMTable('tx_skills_skillset_brand_mm', $uid, $brand['uid']);
    }

    private function setLinkForSkillSet(int $uid, string $uuidLink): void
    {
        $this->setLinkRelation($uid, 'tx_skills_domain_model_skillpath', $uuidLink);
    }

    private function insertSkillSetSkillRelation(int $uid, string $uuidSkill): void
    {
        $skill = $this->findByUUid('tx_skills_domain_model_skill', $uuidSkill);
        $this->insertMMTable('tx_skills_skillpath_skill_mm', $uid, $skill['uid']);
    }

    private function generateSkillRequirements(int $uid, array $requirements): void
    {
        $this->cleanupSkillRequirements($uid);
        foreach ($requirements as $requirement) {
            $r = $this->createNewRequirement($uid);
            foreach ($requirement as $sets) {
                $s = $this->createNewSet($r);
                foreach ($sets as $set) {
                    $lookupSkill = $this->findByUUid('tx_skills_domain_model_skill', $set['skill_uuid']);
                    $this->createNewSetSkill($s, $lookupSkill['uid']);
                }
            }
        }
    }

    private function cleanupSkillRequirements(int $uid): void
    {
        $table = 'tx_skills_domain_model_requirement';
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $qb
            ->delete($table)
            ->where(
                $qb->expr()->eq('skill', $uid)
            )
            ->executeStatement();
    }

    private function createNewRequirement(int $skill): int
    {
        $insertData = [];
        $key = 'NEW1';
        $table = 'tx_skills_domain_model_requirement';
        $insertData[$table][$key] = [
            'skill' => $skill,
            'pid' => $this->pid,
        ];

        $dataHandler = $this->getDataHandler($insertData, []);
        $dataHandler->process_datamap();
        return $dataHandler->substNEWwithIDs[$key];
    }

    private function createNewSet(int $requirement): int
    {
        $insertData = [];
        $key = 'NEW1';
        $table = 'tx_skills_domain_model_set';
        $insertData[$table][$key] = [
            'requirement' => $requirement,
            'pid' => $this->pid,
        ];

        $dataHandler = $this->getDataHandler($insertData, []);
        $dataHandler->process_datamap();
        return $dataHandler->substNEWwithIDs[$key];
    }

    private function createNewSetSkill(int $set, int $skill): int
    {
        $insertData = [];
        $key = 'NEW1';
        $table = 'tx_skills_domain_model_setskill';
        $insertData[$table][$key] = [
            'tx_set' => $set,
            'skill' => $skill,
            'pid' => $this->pid,
        ];

        $dataHandler = $this->getDataHandler($insertData, []);
        $dataHandler->process_datamap();
        return $dataHandler->substNEWwithIDs[$key];
    }

    private function getDataHandler(array $data, array $cmd): ImportDataHandler
    {
        /** @var ImportDataHandler $tce */
        $tce = GeneralUtility::makeInstance(ImportDataHandler::class);
        $tce->isImporting = true;
        $tce->enableLogging = false;
        $tce->dontProcessTransformations = true;
        $tce->checkSimilar = false;
        $tce->start($data, $cmd);

        return $tce;
    }

    private function openLog(string $logFileNamePattern, string $sourceFileName)
    {
        $logFileName = sprintf($logFileNamePattern, $sourceFileName . '_' . date('Y-m-d_H_i_s'));
        $handle = @fopen($logFileName, 'w');
        if (!$handle) {
            throw new RuntimeException('Cannot open log file for writing: ' . $logFileName);
        }
        return $handle;
    }

    /**
     * @param string $path
     * @return false|resource
     */
    private function openSourceFile(string $path)
    {
        return @fopen($path, 'r');
    }

    /**
     * @return false|resource
     */
    private function openStdIn()
    {
        system('stty cbreak');
        return @fopen('php://stdin', 'r');
    }

    private function validateFileIntegrity($sourceFile): bool
    {
        $hash = '';
        $header = '';
        $isFirst = true;
        while (($line = fgets($sourceFile)) !== false) {
            $line = trim($line);
            if ($isFirst) {
                $isFirst = false;
                $header = $line;
                $line = self::emptyHeader();
            }
            $hash = $this->hash($hash . $line);
        }
        return $this->isValidHeader($header, $hash);
    }

    private function log(string $message): void
    {
        if ($this->currentLineNumber) {
            $message = 'Line ' . $this->currentLineNumber . ': ' . $message;
        }
        fwrite($this->logFile, $message . PHP_EOL);
        $this->output->text($message);
    }

    private function logTable(array $rows, array $headers): void
    {
        $this->output->table($headers, $rows);
        foreach (array_merge($headers, $rows) as $row) {
            fwrite($this->logFile, $row . ', ');
        }
        fwrite($this->logFile, PHP_EOL);
    }

    public function startQuery($sql, array $params = null, array $types = null): void
    {
        if (!self::logSqlActive) {
            return;
        }

        $paramsText = '';
        if ($params) {
            $paramsText = ' with ' . LF;
            foreach ($params as $key => $value) {
                $paramsText .= $key . '=' . $value . LF;
            }
        }
        $this->log($sql . $paramsText);
    }

    public function stopQuery() {}
}
