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

namespace SkillDisplay\Skills\Controller;

use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Notification;
use SkillDisplay\Skills\Domain\Repository\NotificationRepository;
use SkillDisplay\Skills\Service\NotificationService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class NotificationController extends AbstractController
{
    protected NotificationRepository $notificationRepository;

    public function __construct(
        NotificationRepository $notificationRepository
    ) {
        $this->notificationRepository = $notificationRepository;
    }

    public function showAction()
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('User not logged in');
        }
        /** @var NotificationService $notificationService */
        $notificationService = GeneralUtility::makeInstance(NotificationService::class);
        $notifications = $notificationService->getNotificationsForUser($user);
        if ($this->view instanceof JsonView) {
            $configuration = [
                'notifications' => [
                    '_descendAll' => [
                        Notification::ApiJsonViewConfiguration
                    ]
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
            $this->view->assign('notifications', $notifications);
        }
    }

    public function deleteNotificationsAction(array $notificationIds)
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('User not logged in');
        }
        /** @var NotificationService $notificationService */
        $notificationService = GeneralUtility::makeInstance(NotificationService::class);
        $notificationService->deleteNotifications($user, $notificationIds);
        $this->view->assign('success', true);
    }
}
