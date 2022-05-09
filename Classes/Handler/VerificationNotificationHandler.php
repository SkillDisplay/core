<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Handler;

use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Notification;
use SkillDisplay\Skills\Domain\Model\User;

class VerificationNotificationHandler implements NotificationHandlerInterface
{
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
        switch ($type) {
            case Notification::TYPE_VERIFICATION_GRANTED:
                return 'Your verification request for ' . $title . ' has been granted.';
            case Notification::TYPE_VERIFICATION_REVOKED:
                return 'Your verification request for ' . $title . ' has been revoked.';
            case Notification::TYPE_VERIFICATION_REJECTED:
                return 'Your verification request for ' . $title . ' has been rejected.';
            case Notification::TYPE_VERIFICATION_REQUESTED:
                return 'You have a new verification request for ' . $title;
        }
        return '';
    }
}
