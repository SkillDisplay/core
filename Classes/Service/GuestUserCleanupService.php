<?php

declare(strict_types=1);

/**
 * This file is part of the "Skills" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Johannes Kasberger <support@reelworx.at>, Reelworx GmbH
 *
 **/

namespace SkillDisplay\Skills\Service;

use DateTime;
use DateTimeImmutable;
use Exception;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Session\Backend\SessionBackendInterface;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GuestUserCleanupService
{
    public const TIME_LIMIT = '-14 days';

    protected SessionBackendInterface $sessionBackend;

    private DateTimeImmutable $limit;

    private array $toDelete = [];

    /**
     * GuestUserCleanupService constructor.
     * @throws Exception
     */
    public function __construct()
    {
        $manager = GeneralUtility::makeInstance(SessionManager::class);
        $this->sessionBackend = $manager->getSessionBackend('FE');

        $now = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'full');
        $this->limit = $now->modify(self::TIME_LIMIT);
    }

    /**
     * @throws Exception
     */
    public function run(): void
    {
        $allSessions = $this->sessionBackend->getAll();
        $allAnonymousUsers = $this->getAllAnonymousUsers();

        // check all active sessions if older than limit
        foreach ($allSessions as $session) {
            $userId = (int)$session['ses_userid'];
            unset($allAnonymousUsers[$userId]);
            $this->checkAndDelete($userId, (int)$session['ses_tstamp'], $session['ses_id']);
        }

        // cleanup all anonymous users that have no session and last login is older than limit
        foreach ($allAnonymousUsers as $userId => $lastLogin) {
            $this->checkAndDelete($userId, $lastLogin);
        }

        // delete user via datahandler
        $dataHandler = $this->getDataHandler($this->toDelete);
        $dataHandler->process_cmdmap();
    }

    /**
     * checks if the timestamp is older than the limit and if the user is anonymous
     * if this is the case the user is deleted
     *
     * @param int $userId
     * @param int $timestamp
     * @param string $sessionId
     * @throws Exception
     */
    private function checkAndDelete(int $userId, int $timestamp, string $sessionId = ''): void
    {
        $sessionTime = new DateTime('@' . $timestamp);
        if ($sessionTime < $this->limit && $this->checkUserIsAnonymous($userId)) {
            $this->deleteUserData($userId, $sessionId);
        }
    }

    /**
     * stores the delete command for the datahandler
     *
     * @param int $userId
     * @param string $sessionId
     */
    private function deleteUserData(int $userId, string $sessionId = ''): void
    {
        if ($sessionId !== '') {
            $this->sessionBackend->remove($sessionId);
        }

        $this->toDelete['fe_users'][$userId]['delete'] = 1;
    }

    /**
     * checks if a user is an anonymous user
     *
     * @param int $userId
     * @return bool
     */
    private function checkUserIsAnonymous(int $userId): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('fe_users');

        $result = $queryBuilder->select('anonymous')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($userId, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return count($result) === 1 && $result[0]['anonymous'];
    }

    /**
     * returns all anonymous users
     *
     * @return array
     */
    private function getAllAnonymousUsers(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('fe_users');

        $result = $queryBuilder->select('uid', 'lastlogin')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq(
                    'anonymous',
                    $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $userAsKey = [];
        foreach ($result as $user) {
            $userAsKey[$user['uid']] = $user['lastlogin'];
        }

        return $userAsKey;
    }

    private function getDataHandler(array $cmd): DataHandler
    {
        /** @var DataHandler $tce */
        $tce = GeneralUtility::makeInstance(DataHandler::class);
        $tce->dontProcessTransformations = true;
        $tce->checkSimilar = false;
        $tce->start([], $cmd);

        return $tce;
    }
}
