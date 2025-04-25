<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Handler;

use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Notification;
use SkillDisplay\Skills\Domain\Model\User;

class VerificationNotificationHandler implements NotificationHandlerInterface
{
    #[\Override]
    public function buildNotification(User $user, string $type, $referenceEntity): Notification
    {
        $notification = new Notification();
        $notification->setCrdate(time());
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setReference((string)$referenceEntity->getUid());
        $notification->setMessage($this->getMessageForType($type, $referenceEntity));

        return $notification;
    }

    private function getMessageForType(string $type, Certification $verification): string
    {
        $title = $verification->getGroupName() ?: $verification->getSkillTitle();
        $message = match ($type) {
            Notification::TYPE_VERIFICATION_GRANTED => 'Your verification request for %s has been granted.',
            Notification::TYPE_VERIFICATION_REVOKED => 'Your verification request for %s has been revoked.',
            Notification::TYPE_VERIFICATION_REJECTED => 'Your verification request for %s has been rejected.',
            Notification::TYPE_VERIFICATION_REQUESTED => 'You have a new verification request for %s',
            default => '',
        };
        return sprintf($message, $title);
    }
}
