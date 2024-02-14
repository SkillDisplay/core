<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Controller;

use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\VerificationCreditPack;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditPackRepository;
use SkillDisplay\Skills\Service\VerificationService;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class VerificationCreditController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly VerificationCreditPackRepository $verificationCreditPackRepository,
        protected readonly VerificationService $verificationService,
    ) {
        parent::__construct($userRepository);
    }

    public function overviewAction(Brand $organisation): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if (!$user || !$user->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('');
        }

        $this->verificationService->setCreditSettings($this->settings['credits']);
        $balance = $this->verificationService->getBalanceForOrganisation($organisation);
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
        return $this->createResponse();
    }

    public function addAction(string $apiKey = ''): ResponseInterface
    {
        // todo new creditpack with 0 points balance! see DataHandlerHook::balanceOpenVerifications
        if ($this->view instanceof JsonView) {

        }
        return $this->createResponse();
    }

    public function listAction(?Brand $organisation, string $apiKey = ''): ResponseInterface
    {
        $user = $this->getCurrentUser(false, $apiKey);
        if (!$user || !$user->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('');
        }
        $packs = [];
        if ($organisation) {
            $packs = $this->verificationCreditPackRepository->findByBrand($organisation)->toArray();
            if ($this->view instanceof JsonView) {
                $configuration = [
                    'packs' => ['_descendAll' => VerificationCreditPack::JsonViewConfiguration],
                ];
                $this->view->setConfiguration($configuration);
                $this->view->setVariablesToRender(array_keys($configuration));
            }
        }
        $this->view->assign('packs', $packs);
        return $this->createResponse();
    }
}
