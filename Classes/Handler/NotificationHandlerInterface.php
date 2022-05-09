<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Handler;

use SkillDisplay\Skills\Domain\Model\Notification;
use SkillDisplay\Skills\Domain\Model\User;

interface NotificationHandlerInterface
{
    public function buildNotification(User $user, string $type, $referenceEntity): Notification;
}
