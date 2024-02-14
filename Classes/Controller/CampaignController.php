<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Controller;

use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\Domain\Model\Campaign;
use SkillDisplay\Skills\Domain\Repository\CampaignRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class CampaignController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly CampaignRepository $campaignRepository
    ) {
        parent::__construct($userRepository);
    }

    public function getForUserAction(string $apiKey): ResponseInterface
    {
        $response = [
            'Version' => '1.0',
            'ErrorMessage' => '',
            'Campaigns' => [],
        ];
        if ($this->view instanceof JsonView) {
            $configuration = [
                'Campaigns' => [
                    '_descendAll' => Campaign::JsonViewConfiguration,
                ],
            ];
            $this->view->setVariablesToRender(['Version', 'ErrorMessage', 'Campaigns']);
            $this->view->setConfiguration($configuration);
        }
        if ($apiKey === '') {
            $response['ErrorMessage'] = 'Missing API key';
            $this->view->assignMultiple($response);
            return $this->createResponse()->withStatus(400, 'Missing API key');
        }
        $userOfRequest = $this->getCurrentUser(false, $apiKey);
        if (!$userOfRequest) {
            $response['ErrorMessage'] = 'Invalid API key';
            $this->view->assignMultiple($response);
            return $this->createResponse()->withStatus(400, 'Invalid API key');
        }
        $campaigns = $this->campaignRepository->findByUserId($userOfRequest->getUid());
        $response['Campaigns'] = $campaigns;
        $this->view->assignMultiple($response);
        return $this->createResponse();
    }
}
