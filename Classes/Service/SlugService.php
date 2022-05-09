<?php declare(strict_types=1);

namespace SkillDisplay\Skills\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SlugService
{
    /** @var SlugHelper[] */
    protected $slugServices;

    protected $tableNames = ['tx_skills_domain_model_skillpath', 'tx_skills_domain_model_skill'];

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
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->eq('path_segment', $queryBuilder->createNamedParameter('', \PDO::PARAM_STR)),
                        $queryBuilder->expr()->isNull('path_segment')
                    )
                )
                ->execute()->fetchColumn(0);
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
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->eq('path_segment', $queryBuilder->createNamedParameter('', \PDO::PARAM_STR)),
                        $queryBuilder->expr()->isNull('path_segment')
                    )
                )
                ->execute();
            while ($record = $statement->fetch()) {
                if ((string)$record[$GLOBALS['TCA'][$table]['columns']['path_segment']['config']['generatorOptions']['fields'][0]] !== '') {
                    $slug = $this->slugServices[$table]->generate($record, $record['pid']);
                    $queryBuilder = $connection->createQueryBuilder();
                    $queryBuilder->update($table)
                                 ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($record['uid'], \PDO::PARAM_INT)))
                                 ->set('path_segment', $this->getUniqueValue($table, $record['uid'], $slug));
                    $databaseQueries[] = $queryBuilder->getSQL();
                    $queryBuilder->execute();
                }
            }
        }

        return $databaseQueries;
    }

    protected function getUniqueValue(string $table, int $uid, string $slug): string
    {
        $statement = $this->getUniqueCountStatement($table, $uid, $slug);
        if ($statement->fetchColumn()) {
            for ($counter = 1; $counter <= 100; $counter++) {
                $newSlug = $slug . '-' . $counter;
                $statement->bindValue(1, $newSlug);
                $statement->execute();
                if (!$statement->fetchColumn()) {
                    break;
                }
            }
        }

        return $newSlug ?? $slug;
    }

    /**
     * @param int $uid
     * @param string $slug
     * @return \Doctrine\DBAL\Driver\Statement|int
     */
    protected function getUniqueCountStatement(string $table, int $uid, string $slug)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        /** @var DeletedRestriction $deleteRestriction */
        $deleteRestriction = GeneralUtility::makeInstance(DeletedRestriction::class);
        $queryBuilder->getRestrictions()->removeAll()->add($deleteRestriction);

        return $queryBuilder
            ->count('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('path_segment',
                    $queryBuilder->createPositionalParameter($slug, \PDO::PARAM_STR)),
                $queryBuilder->expr()->neq('uid', $queryBuilder->createPositionalParameter($uid, \PDO::PARAM_INT))
            )->execute();
    }
}
