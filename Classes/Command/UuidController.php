<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Command;

use SkillDisplay\Skills\Service\TranslatedUuidService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UuidController extends Command
{
    /**
     * Configure the command by defining the name, options and arguments
     */
    #[\Override]
    protected function configure(): void
    {
        $this->setDescription('Checks that all records have a UUID and that translations have the same UUID with isocode suffix.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $iso = [];

        foreach (TranslatedUuidService::UUID_TABLES as $table) {
            $qb = $connection->createQueryBuilder();
            $hasLanguageField = (bool)($GLOBALS['TCA'][$table]['ctrl']['languageField'] ?? false);
            // create uuid for all default language records
            $conditions = [
                'uuid = \'\' or uuid is null',
            ];
            if ($hasLanguageField) {
                $conditions[] = 'sys_language_uid = 0';
            }
            $qb->update($table)->set('uuid', 'uuid()', false)
                ->where(...$conditions)
                ->executeStatement();
            if (!$hasLanguageField) {
                continue;
            }
            $qb->resetQueryParts();
            // fetch all translations
            $res = $qb->select('c.uid', 'c.l10n_parent', 'c.uuid', 'c.sys_language_uid', 'p.uuid as parent_uuid')
                ->from($table, 'c')
                ->join('c', $table, 'p', 'p.uid = c.l10n_parent')
                ->where('c.l10n_parent > 0')
                ->executeQuery();
            while ($row = $res->fetchAssociative()) {
                $isoCode = $iso[$row['sys_language_uid']] ?? ($iso[$row['sys_language_uid']] = TranslatedUuidService::getLanguageIsoCode((int)$row['sys_language_uid']));
                $translationUuid = TranslatedUuidService::getTranslatedUuid($row['parent_uuid'], $isoCode);
                // if uuid does not match the schema <parent-uuid>_<lang-iso2>
                if ($row['uuid'] !== $translationUuid) {
                    $connection->update($table, ['uuid' => $translationUuid], ['uid' => $row['uid']]);
                }
            }
        }
        return Command::SUCCESS;
    }
}
