<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Hook;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Hook functions for record labels
 */
class RecordLabels implements SingletonInterface
{
    public function skill(array &$params): void
    {
        $row = BackendUtility::getRecord($params['table'], $params['row']['uid'], '*', '', false);
        $partable = 'tx_skills_domain_model_tag';
        $par = BackendUtility::getRecord($partable, $row['domain_tag'], '*', '', false);
        $params['title'] = $row['title'];
        if ($par) {
            $params['title'] .= ' (' . BackendUtility::getRecordTitle($partable, $par) . ')';
        }
    }
}
