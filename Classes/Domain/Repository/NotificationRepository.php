<?php
declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Domain\Repository;

use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * The repository for Notifications
 */
class NotificationRepository extends BaseRepository
{

    public function findForUser(User $user): array
    {
        $q = $this->createQuery();
        $q->setOrderings(['crdate' => QueryInterface::ORDER_DESCENDING]);
        $q->matching($q->equals('user', $user));
        return $q->execute()->toArray();
    }

    public function deleteNotification(User $user, int $notificationId)
    {
        $condition = [
            'user' => $user->getUid(),
            'uid' => $notificationId
        ];
        $con = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(
            'tx_skills_domain_model_notification'
        );
        $con->delete('tx_skills_domain_model_notification', $condition);
    }
}
