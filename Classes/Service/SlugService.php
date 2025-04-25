<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Service;

use Doctrine\DBAL\Statement;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SlugService
{
    /** @var SlugHelper[] */
    protected array $slugServices;

    protected array $tableNames = ['tx_skills_domain_model_skillpath', 'tx_skills_domain_model_skill'];

    public function __construct()
    {
        foreach ($this->tableNames as $table) {
            $fieldConfig = $GLOBALS['TCA'][$table]['columns']['path_segment']['config'];
            $this->slugServices[$table] = GeneralUtility::makeInstance(SlugHelper::class, $table, 'path_segment', $fieldConfig);
        }
    }

    public function countOfSlugUpdates(): int
    {
        $elementCount = 0;
        foreach ($this->tableNames as $table) {
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $queryBuilder->getRestrictions()->removeAll();
            $elementCount += $queryBuilder
                ->count('uid')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->eq('path_segment', $queryBuilder->createNamedParameter('')),
                        $queryBuilder->expr()->isNull('path_segment')
                    )
                )
                ->executeQuery()->fetchOne();
        }

        return $elementCount;
    }

    public function performUpdates(): array
    {
        $databaseQueries = [];

        foreach ($this->tableNames as $table) {
            /** @var Connection $connection */
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->getRestrictions()->removeAll();
            $statement = $queryBuilder
                ->select('*')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->eq('path_segment', $queryBuilder->createNamedParameter('')),
                        $queryBuilder->expr()->isNull('path_segment')
                    )
                )
                ->executeQuery();
            while ($record = $statement->fetchAssociative()) {
                if ((string)$record[$GLOBALS['TCA'][$table]['columns']['path_segment']['config']['generatorOptions']['fields'][0]] !== '') {
                    $slug = $this->slugServices[$table]->generate($record, $record['pid']);
                    $queryBuilder = $connection->createQueryBuilder();
                    $queryBuilder->update($table)
                                 ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($record['uid'], Connection::PARAM_INT)))
                                 ->set('path_segment', $this->getUniqueValue($table, $record['uid'], $slug));
                    $databaseQueries[] = $queryBuilder->getSQL();
                    $queryBuilder->executeStatement();
                }
            }
        }

        return $databaseQueries;
    }

    protected function getUniqueValue(string $table, int $uid, string $slug): string
    {
        $statement = $this->getUniqueCountStatement($table, $uid, $slug);
        for ($counter = 1; $counter <= 100 && $statement->executeQuery()->fetchOne(); $counter++) {
            $newSlug = $slug . '-' . $counter;
            $statement->bindValue(1, $newSlug);
        }

        return $newSlug ?? $slug;
    }

    protected function getUniqueCountStatement(string $table, int $uid, string $slug): Statement
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $deleteRestriction = GeneralUtility::makeInstance(DeletedRestriction::class);
        $queryBuilder->getRestrictions()->removeAll()->add($deleteRestriction);

        return $queryBuilder
            ->count('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'path_segment',
                    $queryBuilder->createPositionalParameter($slug)
                ),
                $queryBuilder->expr()->neq('uid', $queryBuilder->createPositionalParameter($uid, Connection::PARAM_INT))
            )->prepare();
    }
}
