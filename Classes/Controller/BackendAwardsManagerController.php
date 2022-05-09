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

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\RewardRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Hook\DataHandlerHook;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class BackendAwardsManagerController extends BackendController
{
    protected function initializeView(ViewInterface $view): void
    {
    }

    protected function generateMenu(): void
    {
    }

    protected function generateButtons(): void
    {
    }

    public function awardsManagerAction(): ?string
    {
        $mainBrandId = DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0;
        if (!$mainBrandId && !$GLOBALS['BE_USER']->isAdmin()) {
            return 'Configuration error. No organisation assigned.';
        }
        /** @var SkillPathRepository $skillSetRepository */
        $skillSetRepository = GeneralUtility::makeInstance(SkillPathRepository::class);
        $skillSets = $skillSetRepository->findSkillPathsOfBrand($mainBrandId);
        $this->view->assign('skillSets', $skillSets);
        return null;
    }

    public function skillSetAwardsAction(SkillPath $skillSet)
    {
        /** @var RewardRepository $rewardRepo */
        $rewardRepo = GeneralUtility::makeInstance(RewardRepository::class);
        $awards = $rewardRepo->getAllForSkillSetWithoutConstraints($skillSet);
        $this->view->assign('awards', $awards);
        $this->view->assign('skillSet', $skillSet);
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     */
    public function toggleAwardActivationAction(Reward $award, SkillPath $skillSet)
    {
        /** @var RewardRepository $rewardRepo */
        $rewardRepo = GeneralUtility::makeInstance(RewardRepository::class);
        $award->setActive($award->getActive() == 1 ? 0 : 1);
        $rewardRepo->update($award);
        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            'tx_skills_domain_model_reward_activation' => [
                'NEW_1' => [
                    'pid' => $award->getPid(),
                    'reward' => $award->getUid(),
                    'active' => $award->getActive()
                ]
            ]
        ], []);
        $dataHandler->process_datamap();
        $this->redirect('skillSetAwards', 'BackendAwardsManager', null, ['skillSet' => $skillSet]);
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     */
    public function createNewAwardAction(SkillPath $skillSet, string $title, string $description)
    {
        /** @var RewardRepository $rewardRepository */
        $rewardRepository = GeneralUtility::makeInstance(RewardRepository::class);
        /** @var Reward $reward */
        $reward = GeneralUtility::makeInstance(Reward::class);
        $reward->setTitle($title);
        $reward->setDescription($description);
        $mainBrandId = DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0;
        $brandRepository = GeneralUtility::makeInstance(BrandRepository::class);
        /** @var Brand $brand */
        $brand = $brandRepository->findByUid($mainBrandId);
        $level = (int)$brand->getFirstCategory()->getDescription();
        $reward->setLevel($level);
        $reward->setSkillpath($skillSet);
        $reward->setType('badge');
        $reward->setActive(1);
        /** @var BrandRepository $brandRepo */
        $brandRepo = GeneralUtility::makeInstance(BrandRepository::class);
        /** @var Brand $brand */
        $brand = $brandRepo->findByUid(DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0);
        if ($brand) {
            $reward->setBrand($brand);
            $reward->setValidForOrganisation($brand);
        }
        $rewardRepository->add($reward);
        $this->redirect('skillSetAwards', 'BackendAwardsManager', null, ['skillSet' => $skillSet]);
    }
}
