<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Controller;

use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Notification;
use SkillDisplay\Skills\Domain\Repository\NotificationRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\NotificationService;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class NotificationController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly NotificationService $notificationService,
        protected readonly NotificationRepository $notificationRepository
    ) {
        parent::__construct($userRepository);
    }

    public function showAction(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('User not logged in');
        }
        $notifications = $this->notificationService->getNotificationsForUser($user);
        if ($this->view instanceof JsonView) {
            $configuration = [
                'notifications' => [
                    '_descendAll' => [
                        Notification::ApiJsonViewConfiguration,
                    ],
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
            $this->view->assign('notifications', $notifications);
        }
        return $this->createResponse();
    }

    public function deleteNotificationsAction(array $notificationIds): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('User not logged in');
        }
        $this->notificationService->deleteNotifications($user, $notificationIds);
        $this->view->assign('success', true);
        return $this->createResponse();
    }
}
