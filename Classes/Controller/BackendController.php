<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Controller;

use DateTime;
use Doctrine\DBAL\DBALException;
use JetBrains\PhpStorm\NoReturn;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\Requirement;
use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\Set;
use SkillDisplay\Skills\Domain\Model\SetSkill;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\RequirementRepository;
use SkillDisplay\Skills\Domain\Repository\RewardRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Hook\DataHandlerHook;
use SkillDisplay\Skills\Service\BackendPageAccessCheckService;
use SkillDisplay\Skills\Service\CsvService;
use SkillDisplay\Skills\Service\TestSystemProviderService;
use SkillDisplay\Skills\Service\VerificationService;
use SkillDisplay\Skills\Service\VerifierPermissionService;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\InvalidNumberOfConstraintsException;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception\UnexpectedTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class BackendController extends ActionController
{
    protected BackendPageAccessCheckService $accessCheck;
    protected ModuleTemplate $moduleTemplate;

    protected int $storagePid = 0;

    protected array $defaultBrands = [];

    protected array $menuItems = [
        'skillUpSplitting' => [
            'controller' => 'Backend',
            'action' => 'skillUpSplitting',
            'label' => 'backend.skillUpSplitting',
        ],
        'reporting' => [
            'controller' => 'Backend',
            'action' => 'reporting',
            'label' => 'backend.reporting',
        ],
    ];

    public function __construct(
        protected readonly SkillPathRepository $skillPathRepository,
        protected readonly SkillRepository $skillRepo,
        protected readonly BrandRepository $brandRepository,
        protected readonly CertificationRepository $certificationRepository,
        protected readonly CertifierRepository $certifierRepository,
        protected readonly RewardRepository $rewardRepository,
        protected readonly RequirementRepository $requirementRepository,
        protected readonly PageRenderer $pageRenderer,
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly VerificationService $verificationService,
    ) {}

    protected function initializeAction(): void
    {
        parent::initializeAction();

        $this->initializeSettings();

        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
    }

    protected function initializeSettings(): void
    {
        $userTsConfig = $GLOBALS['BE_USER']->getTSConfig();
        if (isset($userTsConfig['defaultSkillStoragePid'])) {
            $this->storagePid = (int)$userTsConfig['defaultSkillStoragePid'];
        } else {
            $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
            $settings = $configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
            )['module.']['tx_skills.']['settings.'];
            $this->storagePid = (int)$settings['storagePid'];
        }

        $this->defaultBrands = DataHandlerHook::getDefaultBrandIdsOfBackendUser();

    }

    protected function generateMenu(): void
    {
        if (!$this->menuItems) {
            return;
        }

        $menu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('SkillsModuleMenu');

        foreach ($this->menuItems as $menuItemConfig) {
            $isActive = $this->request->getControllerName() === $menuItemConfig['controller']
                && $this->request->getControllerActionName() === $menuItemConfig['action'];
            $menuItem = $menu->makeMenuItem()
                ->setTitle(self::translate($menuItemConfig['label']))
                ->setHref($this->getHref($menuItemConfig['controller'], $menuItemConfig['action']))
                ->setActive($isActive);
            $menu->addMenuItem($menuItem);
        }

        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    public function skillUpSplittingAction(): ResponseInterface
    {
        $this->skillRepo->setDefaultOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $this->view->assign('skills', $this->skillRepo->findAll());
        return $this->generateOutput();
    }

    /**
     * Moves all verifications on $source skill to the successor skills $targets
     *
     * @param Skill $source
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Skill> $targets
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     */
    public function moveCertificationsAction(Skill $source, $targets): ResponseInterface
    {
        // Attention: do not add a typehint for $targets and do not shorten the FQCN in the phpdoc!

        $this->verificationService->moveVerifications($source, $targets->toArray());

        $uri = $this->uriBuilder->reset()->setCreateAbsoluteUri(true)
            ->uriFor('skillUpSplitting', null, 'Backend');
        return new RedirectResponse($this->addBaseUriIfNecessary($uri), 303);
    }

    /**
     * @throws InvalidQueryException
     */
    public function reportingAction(): ResponseInterface
    {
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Skills/ReportingBackend');

        $this->view->assign('brands', $this->brandRepository->findAllWithMembers()->toArray());
        $this->view->assign('skillSets', $this->skillPathRepository->findAll()->toArray());
        return $this->generateOutput();
    }

    /**
     * @param array $brands
     * @param array $skillSets
     * @param string $dateFrom
     * @param string $dateTo
     * @throws InvalidNumberOfConstraintsException
     * @throws UnexpectedTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    #[NoReturn]
    public function generateReportAction(array $brands, array $skillSets, string $dateFrom, string $dateTo): void
    {
        $lines = [];

        $fromDate = $this->convertDate($dateFrom . ' 00:00:00');
        $toDate = $this->convertDate($dateTo . ' 23:59:59');

        $certifications = $this->certificationRepository->findByGrantDateAndBrandsAndSkillSets($fromDate, $toDate, $brands, $skillSets);

        /** @var Certification $certification */
        foreach ($certifications as $certification) {
            $skill = $certification->getSkill();
            /** @var Brand $brand */
            $brand = $skill && $skill->getBrands()->count() ? $skill->getBrands()[0] : null;
            $lines[] = [
                date('Y-m-d H:i', $certification->getCrdate()),
                $certification->getGrantDate()->format('Y-m-d H:i'),
                $skill ? $skill->getUid() : 0,
                $skill ? $skill->getUUId() : '',
                $skill ? $skill->getTitle() : $certification->getSkillTitle(),
                $skill && $skill->getDomainTag() ? $skill->getDomainTag()->getTitle() : '',
                LocalizationUtility::translate($certification->getLevel() . '.short', 'Skills'),
                $certification->getUser() ? $certification->getUser()->getUsername() : 'deleted user',
                $certification->getUser() ? $certification->getUser()->getFirstName() : 'deleted user',
                $certification->getUser() ? $certification->getUser()->getLastName() : 'deleted user',
                $certification->getCertifier() ? ($certification->getCertifier()->getUser()
                    ? $certification->getCertifier()->getUser()->getUsername()
                    : ($certification->getCertifier()->getTestSystem() ?: 'deleted user'))
                    : 'CertoBot',
                $certification->getBrand() ? $certification->getBrand()->getName() : '',
                $certification->getCampaign() ? $certification->getCampaign()->getTitle() : '',
                $brand ? $brand->getName() : '',
                $brand ? $brand->getPartnerLevel() : '',
            ];
        }

        //set the column names
        array_unshift($lines, [
            'Created',
            'Granted',
            'Skill UID',
            'Skill UUID',
            'Skill',
            'Domain Tag',
            'Level',
            'User',
            'First Name',
            'Last Name',
            'Certifier',
            'Organisation',
            'Campaign',
            'Skill Brand',
            'Skill Brand Partner Level',
        ]);

        $filename = 'Verifications_' . date('YmdHi') . '.csv';

        CsvService::sendCSVFile($lines, $filename);
    }

    private function convertDate(string $date): ?DateTime
    {
        $format = LocalizationUtility::translate('backend.date.date_format-presentation', 'Skills') . ' G:i:s';
        $date = DateTime::createFromFormat($format, $date);
        return $date === false ? null : $date;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws DBALException
     * @throws InvalidQueryException
     * @throws RouteNotFoundException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function ajaxTreeData(ServerRequestInterface $request): ResponseInterface
    {
        $sourceId = $request->getQueryParams()['sourceId'] ?? '0';
        $highlightId = $request->getQueryParams()['highlightId'] ?? '0';
        if (!$sourceId) {
            return (new Response())->withStatus(404);
        }
        $this->accessCheck = new BackendPageAccessCheckService();
        $highlightIds = $this->getSkillIdsByCombinedSource($highlightId);

        $result = [
            'nodes' => [],
            'links' => [],
        ];
        foreach ($this->getSkillIdsByCombinedSource($sourceId) as $skillId) {
            $this->getTreeDataForSkill($skillId, $highlightIds, $result);
        }
        return new JsonResponse($result);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws RouteNotFoundException
     */
    public function ajaxAddSkill(ServerRequestInterface $request): ResponseInterface
    {
        $this->initializeSettings();
        $result = [
            'success' => false,
            'skill' => null,
        ];

        $this->accessCheck = new BackendPageAccessCheckService();
        $valid = true;
        if (!$this->accessCheck->writeAccess($this->storagePid)) {
            $result['error'] = 'Cannot write folder of skill';
            $valid = false;
        }

        $name = $request->getQueryParams()['name'] ?? '';
        if ($valid && $name) {
            $skill = new Skill();
            $skill->setTitle($name);
            $skill->setPid($this->storagePid);
            $skill->setVisibility(Skill::VISIBILITY_ORGANISATION);
            $this->skillRepo->add($skill);

            if ($this->defaultBrands !== []) {
                foreach ($this->defaultBrands as $defaultBrand) {
                    /** @var Brand $brand */
                    $brand = $this->brandRepository->findByUid($defaultBrand);
                    if ($brand) {
                        $skill->addBrand($brand);
                    }
                }
            }

            $this->persistAll();

            $beEditLink = GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('record_edit', [
                'edit' => [
                    'tx_skills_domain_model_skill' => [
                        $skill->getUid() => 'edit',
                    ],
                ],
            ]);

            $result['success'] = true;
            $result['skill'] = [
                'id' => $skill->getUid(),
                'name' => $name,
                'isLocked' => true,
                'icon' => '',
                'beEditLink' => (string)$beEditLink,
                'highlight' => false,
                'incomplete' => true,
                'dormant' => false,
                'brands' => $skill->getBrands(),
            ];
        }
        return new JsonResponse($result);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function ajaxAddLink(ServerRequestInterface $request): ResponseInterface
    {
        $this->initializeSettings();
        $result = [
            'success' => false,
            'link' => null,
            'error' => '',
        ];

        $sourceId = (int)($request->getQueryParams()['sourceId'] ?? 0);
        $targetId = (int)($request->getQueryParams()['targetId'] ?? 0);
        if ($sourceId && $targetId) {
            /** @var Skill $targetSkill */
            $targetSkill = $this->skillRepo->findByUid($targetId);
            /** @var Skill $sourceSkill */
            $sourceSkill = $this->skillRepo->findByUid($sourceId);
            $valid = true;
            // Check if both skills exists
            if (!$sourceSkill || !$targetSkill) {
                $result['error'] = 'Source or target skill do not exist';
                $valid = false;
            }
            if ($valid) {
                // detect existing requirement
                $existingRequirements = $targetSkill->getPrerequisites();
                foreach ($existingRequirements as $requiredSkill) {
                    if ($requiredSkill->getUid() === $sourceId) {
                        $valid = false;
                        $result['error'] = 'Requirement already exists';
                        break;
                    }
                }
            }
            if ($valid) {
                // avoid circular requirements
                $existingRequirements = $sourceSkill->getPrerequisites(true);
                foreach ($existingRequirements as $requiredSkill) {
                    if ($requiredSkill->getUid() === $targetId) {
                        $valid = false;
                        $result['error'] = 'This would create a circle.';
                        break;
                    }
                }
            }

            $this->accessCheck = new BackendPageAccessCheckService();
            if ($valid && !$this->accessCheck->writeAccess($targetSkill->getPid())) {
                $result['error'] = 'Cannot write folder of target';
                $valid = false;
            }
            if ($valid && !$this->accessCheck->readAccess($sourceSkill->getPid())) {
                $result['error'] = 'Cannot read folder of source';
                $valid = false;
            }
            if ($valid &&
                $targetSkill->getVisibility() === Skill::VISIBILITY_PUBLIC &&
                $sourceSkill->getVisibility() === Skill::VISIBILITY_ORGANISATION) {
                $result['error'] = 'Target is public but source is only visible for organisation';
                $valid = false;
            }

            if ($valid) {
                $requirement = new Requirement();
                $set = new Set();
                $setSkill = new SetSkill();

                $requirement->setPid($this->storagePid);
                $set->setPid($this->storagePid);
                $setSkill->setPid($this->storagePid);

                $setSkill->setSkill($sourceSkill);
                $set->addSkill($setSkill);
                $requirement->addSet($set);
                $targetSkill->addRequirement($requirement);

                $this->skillRepo->update($targetSkill);

                $this->persistAll();
                $result['success'] = true;
                $result['link'] = [
                    'id' => $setSkill->getUid(),
                    'source' => $sourceId,
                    'target' => $targetId,
                ];
            }
        } else {
            $result['error'] = 'Missing source or target.';
        }
        return new JsonResponse($result);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function ajaxSetSkillDormant(ServerRequestInterface $request): ResponseInterface
    {
        return $this->deleteOrDormant('dormant', $request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function ajaxDeleteSkill(ServerRequestInterface $request): ResponseInterface
    {
        return $this->deleteOrDormant('delete', $request);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    public function ajaxRemoveSkillFromReward(ServerRequestInterface $request): ResponseInterface
    {
        $result = [
            'success' => false,
            'error' => '',
        ];

        $id = (int)($request->getQueryParams()['id'] ?? 0);
        $rewardId = (int)ltrim($request->getQueryParams()['rewardId'] ?? '', 'r');

        if (!$id || !$rewardId) {
            $result['error'] = 'Missing id parameter.';
            return new JsonResponse($result);
        }

        /** @var Skill $skill */
        $skill = $this->skillRepo->findByUid($id);

        $this->accessCheck = new BackendPageAccessCheckService();
        $valid = true;
        if (!$this->accessCheck->writeAccess($skill->getPid())) {
            $result['error'] = 'Cannot write folder of skill';
            $valid = false;
        }

        if ($valid) {
            /** @var Reward $reward */
            $reward = $this->rewardRepository->findByUid($rewardId);

            foreach ($reward->getPrerequisites() as $pre) {
                if ($pre->getSkill()->getUid() === $id) {
                    $reward->getPrerequisites()->detach($pre);
                    break;
                }
            }
            $this->rewardRepository->update($reward);
            $this->persistAll();

            $result['success'] = true;
        }
        return new JsonResponse($result);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     */
    public function ajaxRemoveRequirement(ServerRequestInterface $request): ResponseInterface
    {
        $result = [
            'success' => false,
            'error' => '',
        ];

        $setSkillId = (int)($request->getQueryParams()['id'] ?? 0);
        if ($setSkillId) {
            $requirement = $this->requirementRepository->findBySetSkillId($setSkillId);
            $this->accessCheck = new BackendPageAccessCheckService();
            if ($requirement && $this->accessCheck->writeAccess($requirement->getPid())) {
                $done = false;
                $sets = $requirement->getSets();
                /** @var Set $set */
                foreach ($sets as $set) {
                    /** @var SetSkill $setSkill */
                    $setSkills = $set->getSkills();
                    foreach ($setSkills as $setSkill) {
                        if ($setSkill->getUid() == $setSkillId) {
                            $set->removeSkill($setSkill);
                            if (!$setSkills->count()) {
                                $requirement->removeSet($set);
                            }
                            $this->requirementRepository->update($requirement);
                            if (!$sets->count()) {
                                $this->requirementRepository->remove($requirement);
                            }
                            $done = true;
                            break;
                        }
                    }
                    if ($done) {
                        break;
                    }
                }
                $this->persistAll();
                $result['success'] = true;
            }
        } else {
            $result['error'] = 'Missing setSkillId.';
        }
        return new JsonResponse($result);
    }

    /**
     * @param SkillPath $skillSet
     * @throws FileDoesNotExistException
     */
    #[NoReturn]
    public function syllabusForSetAction(SkillPath $skillSet): void
    {
        $pdfView = GeneralUtility::makeInstance(StandaloneView::class);
        $pdfView->setTemplatePathAndFilename('EXT:skills/Resources/Private/PdfTemplates/SyllabusForSet.html');
        $skills = $this->skillPathRepository->getSkillsForSyllabusDownload($skillSet);
        if ($skillSet->getSyllabusLayoutFile()) {
            $templateFile = GeneralUtility::makeInstance(ResourceFactory::class)
                ->getFileObject($skillSet->getSyllabusLayoutFile());
            $pdfView->assign('pdfTemplate', $templateFile->getForLocalProcessing(false));
        }
        $pdfView->assign('skills', $skills);
        $pdfView->assign('set', $skillSet);
        $pdfView->render();
    }

    /**
     * @param Reward $reward
     * @throws FileDoesNotExistException
     */
    #[NoReturn]
    public function syllabusAction(Reward $reward): void
    {
        $pdfView = GeneralUtility::makeInstance(StandaloneView::class);
        $pdfView->setTemplatePathAndFilename('EXT:skills/Resources/Private/PdfTemplates/Syllabus.html');
        $skills = [];
        foreach ($reward->getPrerequisites() as $pre) {
            $skill = $pre->getSkill();
            $skills[$skill->getDomainTag() ? $skill->getDomainTag()->getTitle() : '-'][] = $skill;
        }
        if ($skills['-']) {
            $noDomainSkills = $skills['-'];
            unset($skills['-']);
            $skills['-'] = $noDomainSkills;
        }

        if ($reward->getSyllabusLayoutFile()) {
            $templateFile = GeneralUtility::makeInstance(ResourceFactory::class)
                ->getFileObject($reward->getSyllabusLayoutFile());
            $pdfView->assign('pdfTemplate', $templateFile->getForLocalProcessing(false));
        }
        $pdfView->assign('skills', $skills);
        $pdfView->assign('reward', $reward);
        $pdfView->render();
    }

    /**
     * @param Reward $reward
     * @throws FileDoesNotExistException
     */
    #[NoReturn]
    public function completeDownloadAction(Reward $reward): void
    {
        $pdfView = GeneralUtility::makeInstance(StandaloneView::class);
        $pdfView->setTemplatePathAndFilename('EXT:skills/Resources/Private/PdfTemplates/FullDownload.html');
        $skills = [];
        foreach ($reward->getPrerequisites() as $pre) {
            $skill = $pre->getSkill();
            $skills[] = $skill;
        }
        usort($skills, function (Skill $a, Skill $b) {
            return $a->getTitle() <=> $b->getTitle();
        });
        if ($reward->getSyllabusLayoutFile()) {
            $templateFile = GeneralUtility::makeInstance(ResourceFactory::class)
                ->getFileObject($reward->getSyllabusLayoutFile());
            $pdfView->assign('pdfTemplate', $templateFile->getForLocalProcessing(false));
        }
        $pdfView->assign('skills', $skills);
        $pdfView->assign('reward', $reward);
        $pdfView->render();
    }

    /**
     * @param SkillPath $skillSet
     * @throws FileDoesNotExistException
     */
    #[NoReturn]
    public function completeDownloadForSetAction(SkillPath $skillSet): void
    {
        $pdfView = GeneralUtility::makeInstance(StandaloneView::class);
        $pdfView->setTemplatePathAndFilename('EXT:skills/Resources/Private/PdfTemplates/FullDownloadSkillSet.html');
        $skills = $this->skillPathRepository->getSkillsForCompleteDownload($skillSet);
        if ($skillSet->getSyllabusLayoutFile()) {
            $templateFile = GeneralUtility::makeInstance(ResourceFactory::class)
                ->getFileObject($skillSet->getSyllabusLayoutFile());
            $pdfView->assign('pdfTemplate', $templateFile->getForLocalProcessing(false));
        }
        $pdfView->assign('skills', $skills);
        $pdfView->assign('set', $skillSet);
        $pdfView->render();
    }

    public function verifierPermissionsAction(): ResponseInterface
    {
        $verifierList = [];
        foreach ($this->certifierRepository->findAll()->toArray() as $certifier) {
            /** @var Certifier $certifier */
            $verifierList[$certifier->getUid()] = $certifier->getBrand()->getName() .
            ' / ' .
            $certifier->getUser() ? $certifier->getUser()->getUsername() :
                GeneralUtility::makeInstance(TestSystemProviderService::class)
                    ->getProviderById($certifier->getTestSystem())
                    ->getLabel();
        }
        asort($verifierList);
        $this->view->assign('skillSets', $this->skillPathRepository->findAll()->toArray());
        $this->view->assign('verifiers', $verifierList);
        return $this->generateOutput();
    }

    /**
     * @param array $verifiers
     * @param array $skillSets
     * @param string $submitType
     * @param string $tier1
     * @param string $tier2
     * @param string $tier4
     * @return ResponseInterface
     * @throws Exception
     */
    public function modifyPermissionsAction(
        array $verifiers,
        array $skillSets,
        string $submitType,
        string $tier1,
        string $tier2,
        string $tier4
    ): ResponseInterface {
        $permissions = [];
        if ($tier1 === '1') {
            $permissions['tier1'] = 1;
        }
        if ($tier2 === '1') {
            $permissions['tier2'] = 1;
        }
        if ($tier4 === '1') {
            $permissions['tier4'] = 1;
        }

        if (count($verifiers) === 0 || count($skillSets) === 0 || $permissions === []) {
            $this->addFlashMessage('Invalid selection', 'Error', AbstractMessage::ERROR);
        } elseif ($submitType === 'grant') {
            $count = VerifierPermissionService::grantPermissions(array_map('intval', $verifiers), array_map('intval', $skillSets), $permissions);
            $this->addFlashMessage('Granted permissions to ' . $count . ' skill/verifier combinations.');
        } elseif ($submitType === 'revoke') {
            $count = VerifierPermissionService::revokePermissions(array_map('intval', $verifiers), array_map('intval', $skillSets), $permissions);
            $this->addFlashMessage('Revoked permissions from ' . $count . '  skill/verifier combinations.');
        }

        $uri = $this->uriBuilder->reset()->setCreateAbsoluteUri(true)
            ->uriFor('verifierPermissions', null, 'Backend');
        return new RedirectResponse($this->addBaseUriIfNecessary($uri), 303);
    }

    private function getSkillsOfReward(int $rewardId): array
    {
        $skillIds = [];
        /** @var Reward $reward */
        $reward = $this->rewardRepository->findByUid($rewardId);

        if (!$this->accessCheck->readAccess($reward->getPid())) {
            return [];
        }

        foreach ($reward->getPrerequisites() as $prerequisite) {
            $skill = $prerequisite->getSkill();
            if ($this->accessCheck->readAccess($skill->getPid())) {
                $skillIds[] = $skill->getUid();
            }
        }
        return array_unique($skillIds);
    }

    /**
     * @throws InvalidQueryException
     */
    private function getSkillsOfBrand(int $brandId): array
    {
        $skillIds = [];
        $skills = $this->skillRepo->findByBrand($brandId);
        /** @var Skill $skill */
        foreach ($skills as $skill) {
            if ($this->accessCheck->readAccess($skill->getPid())) {
                $skillIds[] = $skill->getUid();
            }
        }
        return array_unique($skillIds);
    }

    /**
     * @param int $tagId
     * @return array
     * @throws InvalidQueryException
     */
    private function getSkillsByTag(int $tagId): array
    {
        $skillIds = [];
        $skills = $this->skillRepo->findByTag($tagId);
        /** @var Skill $skill */
        foreach ($skills as $skill) {
            if ($this->accessCheck->readAccess($skill->getPid())) {
                $skillIds[] = $skill->getUid();
            }
        }
        return array_unique($skillIds);
    }

    private function getSkillsBySet(int $setId): array
    {
        $skillIds = [];
        /** @var SkillPath $skillSet */
        $skillSet = $this->skillPathRepository->findByUid($setId);

        if (!$this->accessCheck->readAccess($skillSet->getPid())) {
            return [];
        }

        foreach ($skillSet->getSkills() as $skill) {
            if ($this->accessCheck->readAccess($skill->getPid())) {
                $skillIds[] = $skill->getUid();
            }
        }
        return array_unique($skillIds);
    }

    /**
     * @param string $combinedId
     * @return array
     * @throws InvalidQueryException
     */
    private function getSkillIdsByCombinedSource(string $combinedId): array
    {
        $id = (int)substr($combinedId, 1);
        return match ($combinedId[0]) {
            's' => [$id],
            'b' => $this->getSkillsOfBrand($id),
            'r' => $this->getSkillsOfReward($id),
            't' => $this->getSkillsByTag($id),
            'p' => $this->getSkillsBySet($id),
            default => [],
        };
    }

    /**
     * @param int $skillId
     * @param array $highlightIds
     * @param array $treeData
     * @throws RouteNotFoundException
     * @throws DBALException
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    private function getTreeDataForSkill(int $skillId, array $highlightIds, array &$treeData): void
    {
        if (!$skillId) {
            return;
        }

        $filteredNodes = array_filter($treeData['nodes'], function (array $element) use ($skillId) {
            return $element['id'] === $skillId;
        });
        if (!empty($filteredNodes)) {
            return;
        }

        /** @var Skill $skill */
        $skill = $this->skillRepo->findByUid($skillId);

        if (!$skill || !$this->accessCheck->readAccess($skill->getPid())) {
            return;
        }

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_skills_domain_model_skill');
        $qb->getRestrictions()->removeAll();
        $qb
            ->select(
                'skill.uid',
                'setskill.uid as linkid',
                'skill.title',
                'skill.icon',
                'setskill.skill',
                'skill.dormant',
                'skill.description',
                'skill.goals',
                'skill.pid'
            )
            ->from('tx_skills_domain_model_skill', 'skill')
            ->leftJoin('skill', 'tx_skills_domain_model_requirement', 'req', 'req.skill = skill.uid')
            ->leftJoin('req', 'tx_skills_domain_model_set', 'set', 'set.requirement = req.uid')
            ->leftJoin('set', 'tx_skills_domain_model_setskill', 'setskill', 'setskill.tx_set = set.uid')
            ->leftJoin('setskill', 'tx_skills_domain_model_skill', 'child', 'setskill.skill = child.uid')
            ->where('skill.uid = ' . $skillId, 'skill.deleted = 0', '(child.uid IS NULL or child.deleted = 0)');
        $children = $qb->executeQuery()->fetchAllAssociative();

        if (empty($children)) {
            return;
        }

        foreach ($children as $key => $child) {
            if (!$this->accessCheck->readAccess((int)$child['pid'])) {
                unset($children[$key]);
            }
        }

        $icon = $children[0]['icon'];
        if (!str_starts_with($icon, 'fa')) {
            $icon = 'fa-' . $icon;
        }

        $beEditLink = '';
        if ($this->accessCheck->writeAccess($skill->getPid())) {
            $beEditLink = GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('record_edit', [
                'edit' => [
                    'tx_skills_domain_model_skill' => [
                        $skillId => 'edit',
                    ],
                ],
            ]);
        }

        $brands = [];
        $skillBrands = $skill->getBrands()->getArray();
        if (count($skillBrands) > 0) {
            foreach ($skillBrands as $brand) {
                $brands[] = [
                    'uid' => $brand->getUid(),
                    'name' => $brand->getName(),
                    'logoPublicUrl' => $brand->getLogoPublicUrl(),
                ];
            }
        }

        $treeData['nodes'][] = [
            'id' => $skillId,
            'name' => $children[0]['title'],
            'isLocked' => true,
            'icon' => $icon,
            'beEditLink' => (string)$beEditLink,
            'highlight' => in_array($skillId, $highlightIds, true),
            'incomplete' => empty($children[0]['description']) || empty($children[0]['goals']),
            'dormant' => (bool)$children[0]['dormant'],
            'brands' => $brands,
        ];

        foreach ($children as $child) {
            $childId = (int)$child['skill'];
            if (!$childId) {
                continue;
            }
            $link = [
                'id' => $child['linkid'],
                'source' => $childId,
                'target' => $skillId,
                'name' => $children[0]['title'],
            ];
            if (!in_array($link, $treeData['links'], true)) {
                $treeData['links'][] = $link;
                $this->getTreeDataForSkill($childId, $highlightIds, $treeData);
            }
        }
    }

    /**
     * Creates te URI for a backend action
     *
     * @param string $controller
     * @param string $action
     * @param array $parameters
     * @return string
     */
    protected function getHref(string $controller, string $action, array $parameters = []): string
    {
        $uriBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);
        $uriBuilder->setRequest($this->request);
        return $uriBuilder->reset()->uriFor($action, $parameters, $controller);
    }

    private static function translate(string $label): ?string
    {
        return LocalizationUtility::translate($label, 'skills');
    }

    /**
     * @param string $type
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     */
    private function deleteOrDormant(string $type, ServerRequestInterface $request): ResponseInterface
    {
        $result = [
            'success' => false,
        ];

        $id = (int)($request->getQueryParams()['id'] ?? 0);

        if (!$id) {
            return new JsonResponse($result);
        }

        /** @var Skill $skill */
        $skill = $this->skillRepo->findByUid($id);

        $this->accessCheck = new BackendPageAccessCheckService();
        $valid = true;
        if (!$this->accessCheck->writeAccess($skill->getPid())) {
            $result['error'] = 'Cannot write folder of skill';
            $valid = false;
        }

        if ($valid) {
            $skill->setRequirements(new ObjectStorage());

            if ($type === 'dormant') {
                $skill->setDormant(new DateTime());
                $this->skillRepo->update($skill);
            } elseif ($type === 'delete') {
                $this->skillRepo->remove($skill);
            }

            /** @var Skill $parentSkill */
            $parentSkills = $this->skillRepo->findParents($skill);
            foreach ($parentSkills as $parentSkill) {
                /** @var Requirement[] $parentRequirements */
                $parentRequirements = $parentSkill->getRequirements();
                foreach ($parentRequirements as $requirement) {
                    /** @var Set $set */
                    $sets = $requirement->getSets();
                    foreach ($sets as $set) {
                        /** @var SetSkill $setSkill */
                        $setSkills = $set->getSkills();
                        foreach ($setSkills as $setSkill) {
                            if ($setSkill->getSkill()->getUid() == $id) {
                                $set->removeSkill($setSkill);
                                if (!$setSkills->count()) {
                                    $requirement->removeSet($set);
                                }
                                if (!$sets->count()) {
                                    $parentSkill->removeRequirement($requirement);
                                }

                                $this->skillRepo->update($parentSkill);
                            }
                        }
                    }
                }
            }
            $this->persistAll();

            $result['success'] = true;
        }
        return new JsonResponse($result);
    }

    protected function persistAll(): void
    {
        GeneralUtility::makeInstance(PersistenceManager::class)->persistAll();
    }

    protected function generateOutput(): ResponseInterface
    {
        $this->generateMenu();
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }
}
