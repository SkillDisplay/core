<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Controller;

use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\VerificationCreditPack;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditPackRepository;
use SkillDisplay\Skills\Service\VerificationService;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class VerificationCreditController extends AbstractController
{
    protected VerificationCreditPackRepository $repo;

    public function __construct(VerificationCreditPackRepository $repo)
    {
        $this->repo = $repo;
    }

    public function overviewAction(Brand $organisation)
    {
        $user = $this->getCurrentUser(false);
        if (!$user || !$user->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('');
        }

        /** @var VerificationService $verificationService */
        $verificationService = $this->objectManager->get(VerificationService::class);
        $verificationService->setCreditSettings($this->settings['credits']);
        $balance = $verificationService->getBalanceForOrganisation($organisation);
        $organisationJson = [
            'uid' => $organisation->getUid(),
            'name' => $organisation->getName(),
            'billable' => $organisation->getBillable(),
            'overdraw' => $organisation->getCreditOverdraw(),
            'points' => $balance['points'],
            'balance' => $balance['balance'],
        ];
        if ($this->view instanceof JsonView) {
            $configuration = [
                'organisation' => [],
            ];
            $this->view->setConfiguration($configuration);
            $this->view->setVariablesToRender(array_keys($configuration));
        }
        $this->view->assign('organisation', $organisationJson);
    }

    /**
     * @param string $apiKey
     */
    public function addAction(string $apiKey = '')
    {
        // todo new creditpack with 0 points balance! see DataHandlerHook::balanceOpenVerifications
        if ($this->view instanceof JsonView) {

        }
    }

    /**
     * @param Brand|null $organisation
     * @param string $apiKey
     */
    public function listAction(Brand $organisation, string $apiKey = '')
    {
        $user = $this->getCurrentUser(false, $apiKey);
        if (!$user || !$user->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('');
        }
        $packs = [];
        if ($organisation) {
            $packs = $this->repo->findByBrand($organisation)->toArray();
            if ($this->view instanceof JsonView) {
                $configuration = [
                    'packs' => ['_descendAll' => VerificationCreditPack::JsonViewConfiguration],
                ];
                $this->view->setConfiguration($configuration);
                $this->view->setVariablesToRender(array_keys($configuration));
            }
        }
        $this->view->assign('packs', $packs);
    }
}
