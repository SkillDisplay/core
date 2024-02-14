<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx
 **/

namespace SkillDisplay\Skills\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendPageAccessCheckService
{
    private array $readCache = [];
    private array $writeCache = [];

    public function readAccess(int $pageUid): bool
    {
        if (!isset($this->readCache[$pageUid])) {
            $page = $this->fetchPage($pageUid);
            if ($page) {
                $this->readCache[$pageUid] = $GLOBALS['BE_USER']->doesUserHaveAccess($page, Permission::PAGE_SHOW);
            } else {
                $this->readCache[$pageUid] = false;
            }
        }
        return $this->readCache[$pageUid];
    }

    public function writeAccess(int $pageUid): bool
    {
        if (!isset($this->writeCache[$pageUid])) {
            $page = $this->fetchPage($pageUid);
            if ($page) {
                $this->writeCache[$pageUid] = $GLOBALS['BE_USER']->doesUserHaveAccess($page, Permission::CONTENT_EDIT);
            } else {
                $this->writeCache[$pageUid] = false;
            }
        }
        return $this->writeCache[$pageUid];
    }

    private function fetchPage(int $pageUid): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $result = $qb->select('*')
            ->from('pages')
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($pageUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        if (count($result) === 1) {
            return $result[0];
        }

        return [];
    }
}
