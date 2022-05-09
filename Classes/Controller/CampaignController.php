<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Controller;

use SkillDisplay\Skills\Domain\Model\Campaign;
use SkillDisplay\Skills\Domain\Repository\CampaignRepository;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class CampaignController extends AbstractController
{
    protected CampaignRepository $campaignRepository;

    public function __construct(CampaignRepository $repo)
    {
        $this->campaignRepository = $repo;
    }

    /**
     * @param string $apiKey
     */
    public function getForUserAction(string $apiKey)
    {
        $response = [
            "Version" => "1.0",
            "ErrorMessage" => "",
            "Campaigns" => [],
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
            $this->response->setStatus(400, 'Missing API key');
            return;
        }
        $userOfRequest = $this->getCurrentUser(false, $apiKey);
        if (!$userOfRequest) {
            $response['ErrorMessage'] = 'Invalid API key';
            $this->view->assignMultiple($response);
            $this->response->setStatus(400, 'Invalid API key');
            return;
        }
        $campaigns = $this->campaignRepository->findByUserId($userOfRequest->getUid());
        $response['Campaigns'] = $campaigns;
        $this->view->assignMultiple($response);
    }
}
