<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 *
 */

namespace SkillDisplay\Skills\Hook;

use SkillDisplay\Skills\Service\BackendPageAccessCheckService;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SkillsProcFunc
{
    public function checkForReadableSkills(array &$configuration): void
    {
        $accessCheck = GeneralUtility::makeInstance(BackendPageAccessCheckService::class);
        $table = 'tx_skills_domain_model_skill';

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $statementSkill = $qb
            ->select('pid')
            ->from($table)
            ->where(
                $qb->expr()->eq('uid', $qb->createPositionalParameter(1, Connection::PARAM_INT))
            )
            ->prepare();

        foreach ($configuration['items'] as $key => $item) {
            $statementSkill->bindValue(1, $item[1]);
            $result = $statementSkill->executeQuery();
            $skill = $result->fetchAllAssociative();
            $result->free();

            if (count($skill) !== 1 || !$accessCheck->readAccess($skill[0]['pid'])) {
                unset($configuration['items'][$key]);
            }
        }
    }

    public function filterBrandsForUser(array &$configuration): void
    {
        $brandIds = DataHandlerHook::getDefaultBrandIdsOfBackendUser();
        if (!$brandIds) {
            return;
        }
        $configuration['items'] = array_filter($configuration['items'], fn(array $item) => in_array($item[1], $brandIds, true));
    }
}
