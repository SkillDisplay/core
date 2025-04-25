<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

readonly class SkillCleanupService
{
    public function __construct(private int $skillId) {}

    /**
     * delete all nested rows that are connected to the skill
     */
    public function doCleanup(): void
    {
        if ($this->skillId > 0) {
            $this->deleteTagRelations();
            $this->deleteRequirements();
            $this->deleteLinks();
        }
    }

    /**
     * deletes all tag mm entries for the skill
     */
    private function deleteTagRelations(): void
    {
        /** @var QueryBuilder $qb */
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_skills_skill_tag_mm');

        $qb = $qb
            ->delete('tx_skills_skill_tag_mm')
            ->where(
                $qb->expr()->eq(
                    'uid_local',
                    $qb->createNamedParameter($this->skillId, Connection::PARAM_INT)
                )
            );

        $qb->executeStatement();
    }

    /**
     * fetches all requirements of the skill to clean up the nested data
     */
    private function deleteRequirements(): void
    {
        $this->fetchAndDeleteNestedData(
            'tx_skills_domain_model_requirement',
            'skill',
            $this->skillId,
            [$this, 'deleteSetsByRequirement']
        );
    }

    /**
     * fetches all sets of the requirement to clean up the nested data
     *
     * @param int $requirementId
     */
    private function deleteSetsByRequirement(int $requirementId): void
    {
        $this->fetchAndDeleteNestedData(
            'tx_skills_domain_model_set',
            'requirement',
            $requirementId,
            [$this, 'deleteSetSkillsBySet']
        );
    }

    /**
     * fetches all skill sets of the set to clean up the nested data
     *
     * @param int $setId
     */
    private function deleteSetSkillsBySet(int $setId): void
    {
        $this->fetchAndDeleteNestedData(
            'tx_skills_domain_model_setskill',
            'tx_set',
            $setId
        );
    }

    /**
     * deletes all assigned links
     */
    private function deleteLinks(): void
    {
        $this->fetchAndDeleteNestedData(
            'tx_skills_domain_model_link',
            'skill',
            $this->skillId
        );
    }

    /**
     * deletes the uids in the list via datahandler
     *
     * @param string $table
     * @param array $uidList
     */
    private function deleteWithDataHandler(string $table, array $uidList): void
    {
        if ($uidList === []) {
            return;
        }

        $cmd = [];

        foreach ($uidList as $uid) {
            $cmd[$table][(string)$uid]['delete'] = 1;
        }

        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->isImporting = true;
        $dataHandler->enableLogging = false;
        $dataHandler->dontProcessTransformations = true;

        $dataHandler->start([], $cmd);

        $dataHandler->process_cmdmap();
    }

    /**
     * fetches the nested data from table/column/key and delete all rows
     *
     * @param string $table
     * @param string $column
     * @param int $key
     * @param callable|null $handleChildren
     */
    private function fetchAndDeleteNestedData(string $table, string $column, int $key, ?callable $handleChildren = null): void
    {
        $result = $this->fetchNestedData($table, $column, $key, $handleChildren);
        $this->deleteWithDataHandler($table, $result);
    }

    private function fetchNestedData(string $table, string $column, int $key, ?callable $handleChildren = null): array
    {
        /** @var QueryBuilder $qb */
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $result = $qb
            ->select('uid')
            ->from($table)
            ->where(
                $qb->expr()->eq(
                    $column,
                    $qb->createNamedParameter($key, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $result = array_column($result, 'uid');

        if ($handleChildren) {
            array_walk($result, $handleChildren);
        }

        return $result;
    }
}
