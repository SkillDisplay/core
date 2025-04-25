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

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\RequirementRepository;
use SkillDisplay\Skills\Domain\Repository\RewardRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Hook\DataHandlerHook;
use SkillDisplay\Skills\Service\BackendPageAccessCheckService;
use SkillDisplay\Skills\Service\VerificationService;
use SkillDisplay\Skills\Service\VerifierPermissionService;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class BackendVerifierController extends BackendController
{
    public function __construct(
        SkillPathRepository $skillPathRepository,
        SkillRepository $skillRepo,
        BrandRepository $brandRepository,
        CertificationRepository $certificationRepository,
        CertifierRepository $certifierRepository,
        RewardRepository $rewardRepository,
        RequirementRepository $requirementRepository,
        PageRenderer $pageRenderer,
        ModuleTemplateFactory $moduleTemplateFactory,
        VerificationService $verificationService,
        protected readonly UserRepository $userRepository,
    ) {
        $this->menuItems = [];
        parent::__construct(
            $skillPathRepository,
            $skillRepo,
            $brandRepository,
            $certificationRepository,
            $certifierRepository,
            $rewardRepository,
            $requirementRepository,
            $pageRenderer,
            $moduleTemplateFactory,
            $verificationService
        );
    }

    /**
     * @throws InvalidQueryException
     */
    #[\Override]
    public function verifierPermissionsAction(): ResponseInterface
    {
        $mainBrandId = DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0;
        if (!$mainBrandId && !$GLOBALS['BE_USER']->isAdmin()) {
            return $this->htmlResponse('Configuration error. No organisation assigned.');
        }

        if ($mainBrandId) {
            /** @var Certifier[] $verifiersOfBrand */
            $verifiersOfBrand = $this->certifierRepository->findByBrandId($mainBrandId)->toArray();
        } else {
            $verifiersOfBrand = $this->certifierRepository->findAll()->toArray();
        }

        $brand = null;
        $verifierList = [];
        foreach ($verifiersOfBrand as $verifier) {
            $brand = $verifier->getBrand();
            $verifierList[$verifier->getUid()] = $verifier->getListLabel();
        }
        asort($verifierList);
        $this->view->assign('verifiers', $verifierList);

        $users = [];
        if ($mainBrandId) {
            // load users of organisations not yet being a verifier for the organisation
            $verifierUserIds = array_map(fn(Certifier $certifier) => $certifier->getUser() ? $certifier->getUser()->getUid() : 0, $verifiersOfBrand);

            /** @var User $user */
            foreach ($this->userRepository->findByOrganisation($mainBrandId) as $user) {
                if (!in_array($user->getUid(), $verifierUserIds)) {
                    $users[$user->getUid()] = $user->getLastName() . ' ' . $user->getFirstName(
                    ) . ' (' . $user->getUsername() . ')';
                }
            }
            asort($users);
        }
        $this->view->assign('users', $users);

        $accessCheckService = GeneralUtility::makeInstance(BackendPageAccessCheckService::class);
        $skillSetsToShow = [];
        if ($mainBrandId) {
            $skillSets = $this->skillPathRepository->findSkillPathsOfBrand($mainBrandId);
        } else {
            $skillSets = $this->skillPathRepository->findAll();
        }
        /** @var SkillPath $skillSet */
        foreach ($skillSets as $skillSet) {
            if (!$accessCheckService->readAccess($skillSet->getPid())) {
                continue;
            }
            $skillSetsToShow[] = $skillSet;
        }
        $this->view->assign('skillSets', $skillSetsToShow);

        if ($mainBrandId) {
            $allowedTiers = $brand ? $this->getAllowedTiers($brand) : [];
        } else {
            $allowedTiers = [1, 2, 4];
        }
        $this->view->assign('allowedTiers', $allowedTiers);

        return $this->generateOutput();
    }

    /**
     * @param int[] $verifiers
     * @param int[] $skillSets
     * @param string $submitType
     * @param string $tier1
     * @param string $tier2
     * @param string $tier4
     * @return ResponseInterface
     */
    #[\Override]
    public function modifyPermissionsAction(
        array $verifiers,
        array $skillSets,
        string $submitType,
        string $tier1 = '',
        string $tier2 = '',
        string $tier4 = ''
    ): ResponseInterface {
        $allowedTiers = [];
        $mainBrandId = DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0;
        if ($mainBrandId) {
            $brand = null;
            foreach ($verifiers as $verifierId) {
                /** @var Certifier $verifier */
                $verifier = $this->certifierRepository->findByUid($verifierId);
                $brand = $verifier->getBrand();
                if ($verifier->getBrand()->getUid() !== $mainBrandId) {
                    throw new InvalidArgumentException('The passed verifier is not part of the organisation', 9139931576);
                }
            }
            $allowedTiers = $brand ? $this->getAllowedTiers($brand) : [];
        } elseif ($GLOBALS['BE_USER']->isAdmin()) {
            $allowedTiers = [1, 2, 4];
        }

        $permissions = [];
        if ($tier1 === '1' && in_array(1, $allowedTiers)) {
            $permissions['tier1'] = 1;
        }
        if ($tier2 === '1' && in_array(2, $allowedTiers)) {
            $permissions['tier2'] = 1;
        }
        if ($tier4 === '1' && in_array(4, $allowedTiers)) {
            $permissions['tier4'] = 1;
        }

        if (count($verifiers) === 0 || count($skillSets) === 0 || $permissions === []) {
            $this->addFlashMessage('Invalid selection', 'Error', ContextualFeedbackSeverity::ERROR);
        } elseif ($submitType === 'grant') {
            $count = VerifierPermissionService::grantPermissions(array_map('intval', $verifiers), array_map('intval', $skillSets), $permissions);
            $this->addFlashMessage('Granted permissions to ' . $count . ' skill/verifier combinations.');
        } elseif ($submitType === 'revoke') {
            $count = VerifierPermissionService::revokePermissions(array_map('intval', $verifiers), array_map('intval', $skillSets), $permissions);
            $this->addFlashMessage('Revoked permissions from ' . $count . '  skill/verifier combinations.');
        }

        $uri = $this->uriBuilder->uriFor('verifierPermissions', null, 'BackendVerifier');
        return new RedirectResponse($this->addBaseUriIfNecessary($uri), 303);
    }

    public function addVerifierAction(User $user): ResponseInterface
    {
        $mainBrandId = DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0;
        if ($mainBrandId) {
            foreach ($user->getOrganisations() as $organisation) {
                if ($organisation->getUid() === $mainBrandId) {
                    /** @var ?Brand $brand */
                    $brand = $this->brandRepository->findByUid($mainBrandId);

                    $verifier = new Certifier();
                    $verifier->setPid($organisation->getPid());
                    $verifier->setUser($user);
                    $verifier->setBrand($brand);

                    $this->certifierRepository->add($verifier);
                }
            }
        }
        $uri = $this->uriBuilder->uriFor('verifierPermissions', null, 'BackendVerifier');
        return new RedirectResponse($this->addBaseUriIfNecessary($uri), 303);
    }

    /**
     * @param Brand $organisation
     * @return int[]
     */
    protected function getAllowedTiers(Brand $organisation): array
    {
        $allowedTiers = [];
        /** @var Category $category */
        foreach ($organisation->getCategories() as $category) {
            if ((int)$category->getDescription()) {
                $allowedTiers[] = (int)$category->getDescription();
            }
        }
        $allowedTiers[] = 2;
        $allowedTiers = array_unique($allowedTiers);
        asort($allowedTiers);
        return $allowedTiers;
    }
}
