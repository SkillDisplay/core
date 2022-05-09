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

namespace SkillDisplay\Skills\Service;

use InvalidArgumentException;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\NotificationRepository;
use SkillDisplay\Skills\Handler\NotificationHandlerInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;

class NotificationService implements SingletonInterface
{
    /** @var NotificationHandlerInterface[] */
    protected static array $handlers = [];

    protected NotificationRepository $notificationRepository;

    public function __construct(NotificationRepository $notificationRepository)
    {
        $this->notificationRepository = $notificationRepository;
    }

    public static function registerHandler(string $type, NotificationHandlerInterface $handler): void
    {
        self::$handlers[$type] = $handler;
    }

    /**
     * @throws IllegalObjectTypeException
     */
    public function addNotification(User $user, string $type, $referenceEntity)
    {
        $handler = self::$handlers[$type] ?? null;
        if (!$handler) {
            throw new InvalidArgumentException('Given type "' . $type . '" has no registered handler.', 1474505954);
        }
        $notification = $handler->buildNotification($user, $type, $referenceEntity);
        $this->notificationRepository->add($notification);
    }

    public function getNotificationsForUser(User $user): array
    {
        return $this->notificationRepository->findForUser($user);
    }

    public function deleteNotifications(User $user, array $notificationIds): void
    {
        foreach ($notificationIds as $notificationId) {
            if ($notificationId) {
                $this->notificationRepository->deleteNotification($user, (int)$notificationId);
            }
        }
    }
}
