<?php
declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Reelworx\TYPO3\MailService\MailConfiguration;
use Reelworx\TYPO3\MailService\MailService;
use SkillDisplay\Skills\Domain\Model\Notification;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Event\VerificationAddedEvent;
use SkillDisplay\Skills\Event\VerificationUpdatedEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Exception\InvalidControllerNameException;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Fluid\View\StandaloneView;

class CertoBot implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Auto-grant Self-Skillups
     *
     * @param VerificationAddedEvent $event
     * @throws Exception
     * @throws InvalidQueryException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function selfVerificationHandler(VerificationAddedEvent $event): void
    {
        $verifications = $event->getVerifications();
        if ($verifications[0]->getTier3()) {
            /** @var VerificationService $verificationService */
            $verificationService = GeneralUtility::makeInstance(ObjectManager::class)->get(VerificationService::class);
            $verificationService->confirmSkillUp($verifications[0], true);
        }
    }

    /**
     * @throws IllegalObjectTypeException
     */
    public function addVerifierNotification(VerificationAddedEvent $event): void
    {
        $certification = $event->getVerifications()[0];
        if (!$certification->getCertifier() || !$certification->isPending()) {
            return;
        }
        $user = $certification->getCertifier()->getUser();
        if (!$user) {
            return;
        }

        /** @var NotificationService $notificationService */
        $notificationService = GeneralUtility::makeInstance(NotificationService::class);
        $notificationService->addNotification(
            $user,
            Notification::TYPE_VERIFICATION_REQUESTED,
            $certification
        );
    }

    /**
     * @throws IllegalObjectTypeException
     */
    public function addUserVerificationNotification(VerificationUpdatedEvent $event): void
    {
        $certification = $event->getVerifications()[0];
        if ($certification->getLevelNumber() === 3) {
            return;
        }
        $user = $certification->getUser();
        if (!$user) {
            return;
        }
        /** @var NotificationService $notificationService */
        $notificationService = GeneralUtility::makeInstance(NotificationService::class);
        if ($certification->getGrantDate() && !$certification->getRevokeDate()) {
            $notificationService->addNotification(
                $user,
                Notification::TYPE_VERIFICATION_GRANTED,
                $certification
            );
        } elseif ($certification->getRevokeDate()) {
            $notificationService->addNotification(
                $user,
                Notification::TYPE_VERIFICATION_REVOKED,
                $certification
            );
        } elseif ($certification->getDenyDate()) {
            $notificationService->addNotification(
                $user,
                Notification::TYPE_VERIFICATION_REJECTED,
                $certification
            );
        }
    }

    /**
     * @param VerificationAddedEvent $event
     * @throws Exception
     * @throws InvalidControllerNameException
     */
    public function sendCertificationRequestedMail(VerificationAddedEvent $event): void
    {
        $certification = $event->getVerifications()[0];
        if (!$certification->getCertifier() || !$certification->isPending()) {
            return;
        }
        $user = $certification->getCertifier()->getUser();
        if (!$user || !$user->isMailPush()) {
            return;
        }

        $language = $user->getMailLanguage();

        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $configurationManager = $objectManager->get(ConfigurationManagerInterface::class);
        $settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Skills'
        );

        $mailConfig = MailConfiguration::fromArray($settings['mail']);
        $mailConfig->controllerName = 'Skill';
        $mailService = GeneralUtility::makeInstance(MailService::class, $mailConfig);
        $msg = $mailService->createMessage();

        $mailView = $mailService->createMailView($msg);

        $mailView->assign('settings', $settings);
        $mailView->assign('user', $user);
        $mailView->assign('certification', $certification);

        $msg->setContent($mailService->renderMail($mailView, 'skillUpRequest', $language));
        $msg->setTo($user->getEmail());
        $msg->send();
    }

    /**
     * @param VerificationUpdatedEvent $event
     * @throws Exception
     * @throws InvalidControllerNameException
     */
    public function sendCertificationCompletedMail(VerificationUpdatedEvent $event): void
    {
        $certification = $event->getVerifications()[0];
        $user = $certification->getUser();
        if (!$user->isMailPush() || !$certification->getCertifier() || $certification->getRevokeDate()) {
            return;
        }

        $language = $user->getMailLanguage();

        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $configurationManager = $objectManager->get(ConfigurationManagerInterface::class);
        $settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Skills'
        );

        $mailConfig = MailConfiguration::fromArray($settings['mail']);
        $mailConfig->controllerName = 'Skill';
        $mailService = GeneralUtility::makeInstance(MailService::class, $mailConfig);
        $msg = $mailService->createMessage();

        $mailView = $mailService->createMailView($msg);

        $mailView->assign('settings', $settings);
        $mailView->assign('user', $user);
        $mailView->assign('certification', $certification);

        $msg->setContent($mailService->renderMail($mailView, 'skillUp', $language));
        $msg->setTo($user->getEmail());
        $msg->send();
    }

    public function updateRecommendations(VerificationUpdatedEvent $event): void
    {
        $relationService = GeneralUtility::makeInstance(SkillSetRelationService::class);
        $user = null;
        $affectedSkillSets = [];
        foreach ($event->getVerifications() as $verification) {
            $user = $verification->getUser();
            $skillSetsIncludingSkill = GeneralUtility::makeInstance(SkillPathRepository::class)->findBySkill(
                $verification->getSkill()
            );
            foreach ($skillSetsIncludingSkill as $skillSet) {
                $affectedSkillSets[$skillSet->getUid()] = $skillSet;
            }
        }

        foreach ($affectedSkillSets as $skillSet) {
            try {
                $relationService->calculateScoresBySourceSet($user, $skillSet);
            } catch (\Doctrine\DBAL\Driver\Exception|InvalidQueryException $e) {
                $this->logger->critical('Failed to update recommendations for skillset', [
                    'exception' => $e,
                    'skillset' => $skillSet
                ]);
            }
        }
    }

    public static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
