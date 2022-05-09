<?php declare(strict_types=1);

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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SkillsProcFunc
{
    public function checkForReadableSkills(array &$configuration)
    {
        $accessCheck = GeneralUtility::makeInstance(BackendPageAccessCheckService::class);
        $table = 'tx_skills_domain_model_skill';

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $statementSkill = $qb
            ->select('pid')
            ->from($table)
            ->where(
                $qb->expr()->eq('uid', $qb->createPositionalParameter(1, \PDO::PARAM_INT))
            )
            ->execute();

        foreach ($configuration['items'] as $key => $item) {

            $statementSkill->bindValue(1, $item[1]);
            $statementSkill->execute();
            $skill = $statementSkill->fetchAll();
            $statementSkill->closeCursor();

            if (count($skill) !== 1 || !$accessCheck->readAccess($skill[0]['pid'])) {
                unset($configuration['items'][$key]);
            }
        }
    }
}
