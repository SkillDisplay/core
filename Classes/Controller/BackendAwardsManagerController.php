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
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Hook\DataHandlerHook;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BackendAwardsManagerController extends BackendController
{
    protected array $menuItems = [];

    public function awardsManagerAction(): ResponseInterface
    {
        $mainBrandId = DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0;
        if (!$mainBrandId && !$GLOBALS['BE_USER']->isAdmin()) {
            return $this->htmlResponse('Configuration error. No organisation assigned.');
        }
        $skillSets = $this->skillPathRepository->findSkillPathsOfBrand($mainBrandId);
        $this->view->assign('skillSets', $skillSets);
        return $this->generateOutput();
    }

    public function skillSetAwardsAction(SkillPath $skillSet): ResponseInterface
    {
        $awards = $this->rewardRepository->getAllForSkillSetWithoutConstraints($skillSet);
        $this->view->assign('awards', $awards);
        $this->view->assign('skillSet', $skillSet);
        return $this->generateOutput();
    }

    public function toggleAwardActivationAction(Reward $award, SkillPath $skillSet): ResponseInterface
    {
        $award->setActive($award->getActive() == 1 ? 0 : 1);
        $this->rewardRepository->update($award);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'tx_skills_domain_model_reward_activation' => [
                'NEW_1' => [
                    'pid' => $award->getPid(),
                    'reward' => $award->getUid(),
                    'active' => $award->getActive(),
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        $uri = $this->uriBuilder->uriFor('skillSetAwards', ['skillSet' => $skillSet], 'BackendAwardsManager');
        return new RedirectResponse($this->addBaseUriIfNecessary($uri), 303);
    }

    public function createNewAwardAction(SkillPath $skillSet, string $title, string $description): ResponseInterface
    {
        $reward = new Reward();
        $reward->setTitle($title);
        $reward->setDescription($description);
        $mainBrandId = DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0;

        $brand = $this->brandRepository->findByUid($mainBrandId);
        $level = (int)$brand->getFirstCategory()->getDescription();
        $reward->setLevel($level);
        $reward->setSkillpath($skillSet);
        $reward->setType('badge');
        $reward->setActive(1);

        /** @var Brand $brand */
        $brand = $this->brandRepository->findByUid(DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0);
        if ($brand) {
            $reward->setBrand($brand);
            $reward->setValidForOrganisation($brand);
        }
        $this->rewardRepository->add($reward);

        $uri = $this->uriBuilder->uriFor('skillSetAwards', ['skillSet' => $skillSet], 'BackendAwardsManager');
        return new RedirectResponse($this->addBaseUriIfNecessary($uri), 303);
    }
}
