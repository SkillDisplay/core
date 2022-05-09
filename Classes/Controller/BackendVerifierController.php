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
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Hook\DataHandlerHook;
use SkillDisplay\Skills\Service\BackendPageAccessCheckService;
use SkillDisplay\Skills\Service\VerifierPermissionService;
use SkillDisplay\Skills\Service\TestSystemProviderService;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Object\Exception;

class BackendVerifierController extends BackendController
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

    public function verifierPermissionsAction(): ?string
    {
        $mainBrandId = DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0;
        if (!$mainBrandId && !$GLOBALS['BE_USER']->isAdmin()) {
            return 'Configuration error. No organisation assigned.';
        }

        $certifierRepository = $this->objectManager->get(CertifierRepository::class);
        $verifierList = [];

        /** @var Certifier[] $verifiersOfBrand */
        if ($mainBrandId) {
            $verifiersOfBrand = $certifierRepository->findByBrandId($mainBrandId)->toArray();
        } else {
            $verifiersOfBrand = $certifierRepository->findAll()->toArray();
        }
        $brand = null;
        $providerService = GeneralUtility::makeInstance(TestSystemProviderService::class);
        foreach ($verifiersOfBrand as $verifier) {
            $brand = $verifier->getBrand();
            if ($verifier->getUser()) {
                $verifierList[$verifier->getUid()] = $verifier->getBrand()->getName() .
                                                      ' / ' .
                                                      $verifier->getUser()->getUsername();
            } else {
                $verifierList[$verifier->getUid()] = $verifier->getBrand()->getName() .
                                                      ' / ' .
                                                      $providerService->getProviderById($verifier->getTestSystem())->getLabel();
            }
        }
        asort($verifierList);
        $this->view->assign('verifiers', $verifierList);

        $users = [];
        if ($mainBrandId) {
            // load users of organisations not yet being a verifier for the organisation
            /** @var UserRepository $userRepo */
            $userRepo = $this->objectManager->get(UserRepository::class);
            $verifierUserIds = array_map(function (Certifier $certifier) {
                return $certifier->getUser() ? $certifier->getUser()->getUid() : 0;
            }, $verifiersOfBrand);

            $users = [];
            /** @var User $user */
            foreach ($userRepo->findByOrganisation($mainBrandId) as $user) {
                if (!in_array($user->getUid(), $verifierUserIds)) {
                    $users[$user->getUid()] = $user->getLastName() . ' ' . $user->getFirstName() . ' (' . $user->getUsername() . ')';
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

        return null;
    }

    /**
     * @param array $verifiers
     * @param array $skillSets
     * @param string $submitType
     * @param string $tier1
     * @param string $tier2
     * @param string $tier4
     * @throws StopActionException
     * @throws Exception
     */
    public function modifyPermissionsAction(
        array $verifiers,
        array $skillSets,
        string $submitType,
        string $tier1 = '',
        string $tier2 = '',
        string $tier4 = ''
    ) {
        $allowedTiers = [];
        $mainBrandId = DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0;
        if ($mainBrandId) {
            $brand = null;
            $certifierRepository = $this->objectManager->get(CertifierRepository::class);
            foreach ($verifiers as $verifierId) {
                /** @var Certifier $verifier */
                $verifier = $certifierRepository->findByUid($verifierId);
                $brand = $verifier->getBrand();
                if ($verifier->getBrand()->getUid() !== $mainBrandId) {
                    throw new \InvalidArgumentException('The passed verifier is not part of the organisation');
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
            $this->addFlashMessage('Invalid selection', 'Error', AbstractMessage::ERROR);
        } else {
            if ($submitType === 'grant') {
                $count = VerifierPermissionService::grantPermissions($verifiers, $skillSets, $permissions);
                $this->addFlashMessage('Granted permissions to ' . $count . ' skill/verifier combinations.');
            } elseif ($submitType === 'revoke') {
                $count = VerifierPermissionService::revokePermissions($verifiers, $skillSets, $permissions);
                $this->addFlashMessage('Revoked permissions from ' . $count . '  skill/verifier combinations.');
            }
        }

        $this->redirect('verifierPermissions');
    }

    /**
     * @param User $user
     * @throws StopActionException
     */
    public function addVerifierAction(User $user)
    {
        $mainBrandId = DataHandlerHook::getDefaultBrandIdsOfBackendUser()[0] ?? 0;
        if ($mainBrandId) {
            foreach ($user->getOrganisations() as $organisation) {
                if ($organisation->getUid() === $mainBrandId) {
                    $brandRepo = $this->objectManager->get(BrandRepository::class);
                    $brand = $brandRepo->findByUid($mainBrandId);

                    $verifier = new Certifier();
                    $verifier->setPid($organisation->getPid());
                    $verifier->setUser($user);
                    $verifier->setBrand($brand);

                    $certifierRepository = $this->objectManager->get(CertifierRepository::class);
                    $certifierRepository->add($verifier);
                }
            }
        }
        $this->redirect('verifierPermissions');
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
