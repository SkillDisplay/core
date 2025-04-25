<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH, Johannes Kasberger
 **/

namespace SkillDisplay\Skills\Controller;

use Doctrine\DBAL\Driver\Exception;
use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class PaymentController extends AbstractController
{
    public function getSubscriptionAction(Brand $organisation): ResponseInterface
    {
        $loggedInUser = $this->getCurrentUser();
        if (!$loggedInUser) {
            throw new AuthenticationException('', 7429003113);
        }

        $data = [
            'subscriptionActive' => false,
            'managerName' => '',
            'error' => '',
            'manageButtonVisible' => false,
        ];

        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(array_keys($data));
        }

        if ($loggedInUser->getManagedBrands()->contains($organisation)) {
            if (class_exists(\Stripe\StripeClient::class) && $this->settings['stripeKey']) {
                $client = new \Stripe\StripeClient($this->settings['stripeKey']);
                $subscriptionId = $this->getSubscriptionId($organisation);
                if ($subscriptionId) {
                    $subscription = $client->subscriptions->retrieve($subscriptionId, ['expand' => ['customer']]);
                    $user = $this->getUserByStripeId($subscription->customer->id);
                    $data['subscriptionActive'] = true;
                    if ($user !== []) {
                        $data['managerName'] = $user['first_name'] . ' ' . $user['last_name'];
                        $managerUid = (int)$user['uid'];
                        $data['manageButtonVisible'] = $managerUid > 0 && $managerUid === $loggedInUser->getUid();
                    } else {
                        $data['error'] = 'Manager not found';
                    }
                }
            } else {
                $data['error'] = 'Stripe not active';
            }
        } else {
            $data['error'] = 'User is not a manager of this organisation';
        }

        $this->view->assignMultiple($data);
        return $this->createResponse();
    }

    /**
     * @throws Exception
     */
    public function getCustomerPortalUrlAction(string $returnUrl): ResponseInterface
    {
        if ($this->view instanceof JsonView) {
            $configuration = [
                'error' => [],
                'portalUrl' => [],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }

        $loggedInUser = $this->getCurrentUser();
        if (!$loggedInUser) {
            throw new AuthenticationException('', 4228675771);
        }

        $error = '';
        $portalUrl = '';

        $client = null;
        if (class_exists(\Stripe\StripeClient::class) && $this->settings['stripeKey']) {
            $client = new \Stripe\StripeClient($this->settings['stripeKey']);
        }
        if ($client) {
            $rawUser = $this->getUserByUid($loggedInUser->getUid());

            if (empty($rawUser['stripe_user'])) {
                $error = 'Not a valid stripe user';
            } else {
                $session = $client->billingPortal->sessions->create(
                    [
                        'customer' => $rawUser['stripe_user'],
                        'return_url' => $returnUrl,
                    ]
                );
                $portalUrl = $session->url;
            }
        } else {
            $error = 'Stripe not active';
        }

        $data = [
            'portalUrl' => $portalUrl,
            'error' => $error,
        ];

        $this->view->assignMultiple($data);
        return $this->createResponse();
    }

    private function getSubscriptionId(Brand $organisation): string
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_skills_domain_model_brand');
        $brandData = $queryBuilder->select('stripe_subscription')
            ->from('tx_skills_domain_model_brand')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($organisation->getUid(), Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        if (count($brandData) === 1) {
            return $brandData[0]['stripe_subscription'];
        }
        return '';
    }

    private function getUserByStripeId(string $stripeId): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('fe_users');
        $userData = $queryBuilder->select('*')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('stripe_user', $queryBuilder->createNamedParameter($stripeId))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        if (count($userData) === 1) {
            return $userData[0];
        }
        return [];
    }

    private function getUserByUid(int $uid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('fe_users');
        $userData = $queryBuilder->select('*')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        if (count($userData) === 1) {
            return $userData[0];
        }
        return [];
    }
}
