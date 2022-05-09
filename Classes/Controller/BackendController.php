<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Requirement;
use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\Set;
use SkillDisplay\Skills\Domain\Model\SetSkill;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\RequirementRepository;
use SkillDisplay\Skills\Domain\Repository\RewardRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\TagRepository;
use SkillDisplay\Skills\Service\BackendPageAccessCheckService;
use SkillDisplay\Skills\Service\CsvService;
use SkillDisplay\Skills\Service\VerifierPermissionService;
use SkillDisplay\Skills\Service\TestSystemProviderService;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class BackendController extends ActionController
{
    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /**
     * BackendTemplateContainer
     *
     * @var BackendTemplateView
     */
    protected $view;

    protected int $storagePid = 0;

    protected array $defaultBrands = [];

    protected BackendPageAccessCheckService $accessCheck;

    protected DataMapper $dataMapper;

    protected SkillPathRepository $skillPathRepository;

    protected SkillRepository $skillRepo;

    protected BrandRepository $brandRepository;

    public function __construct(DataMapper $dataMapper,
                                ObjectManager $objectManager,
                                SkillRepository $skillRepo,
                                BrandRepository $brandRepository,
                                SkillPathRepository $skillPathRepository)
    {
        $this->dataMapper = $dataMapper;
        $this->objectManager = $objectManager;
        $this->skillRepo = $skillRepo;
        $this->brandRepository = $brandRepository;
        $this->skillPathRepository = $skillPathRepository;
    }

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     * @return void
     */
    protected function initializeView(ViewInterface $view): void
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        if ($view instanceof BackendTemplateView) {
            $this->generateMenu();
            $this->generateButtons();
            $this->view->getModuleTemplate()->setFlashMessageQueue($this->controllerContext->getFlashMessageQueue());
        }
    }

    protected function initializeAction()
    {
        parent::initializeAction();
        $userTsConfig = $GLOBALS['BE_USER']->getTSConfig();

        if (isset($userTsConfig['defaultSkillStoragePid'])) {
            $this->storagePid = (int)$userTsConfig['defaultSkillStoragePid'];
        } else {
            $configurationManager = $this->objectManager->get(ConfigurationManager::class);
            $settings = $configurationManager->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_FULL_TYPOSCRIPT)['module.']['tx_skills.']['settings.'];
            $this->storagePid = (int)$settings['storagePid'];
        }

        if (isset($userTsConfig['TCAdefaults.']['tx_skills_domain_model_skill.']['brands'])) {
            $this->defaultBrands = GeneralUtility::intExplode(',',
                $userTsConfig['TCAdefaults.']['tx_skills_domain_model_skill.']['brands']);
        }
    }

    protected function generateMenu(): void
    {
        $menuItems = [];
        $menuItems['skillUpSplitting'] = [
            'controller' => 'Backend',
            'action' => 'skillUpSplitting',
            'label' => 'backend.skillUpSplitting',
        ];
        $menuItems['reporting'] = [
            'controller' => 'Backend',
            'action' => 'reporting',
            'label' => 'backend.reporting',
        ];

        $menu = $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('SkillsModuleMenu');

        foreach ($menuItems as $menuItemConfig) {
            $isActive = $this->request->getControllerName() === $menuItemConfig['controller']
                && $this->request->getControllerActionName() === $menuItemConfig['action'];
            $menuItem = $menu->makeMenuItem()
                ->setTitle($this->translate($menuItemConfig['label']))
                ->setHref($this->getHref($menuItemConfig['controller'], $menuItemConfig['action']))
                ->setActive($isActive);
            $menu->addMenuItem($menuItem);
        }

        $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    protected function generateButtons(): void
    {
        $buttonBar = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        $buttons = [];

        $storagePid = $this->settings['storagePid'];

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        foreach ($buttons as $button) {
            $table = $button['table'];
            $title = $this->translate($button['label']);
            $icon = $button['icon'];
            $url = $uriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [
                    $table => [
                        $storagePid => 'new',
                    ],
                ],
                'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
            ]);

            $viewButton = $buttonBar->makeLinkButton()
                ->setHref((string)$url)
                ->setDataAttributes([
                    'toggle' => 'tooltip',
                    'placement' => 'bottom',
                    'title' => $title,
                ])
                ->setTitle($title)
                ->setIcon($iconFactory->getIcon($icon, Icon::SIZE_SMALL));
            $buttonBar->addButton($viewButton, ButtonBar::BUTTON_POSITION_LEFT);
        }
    }

    public function skillUpSplittingAction()
    {
        $this->skillRepo->setDefaultOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $this->view->assign('skills', $this->skillRepo->findAll());
    }

    /**
     * @param Skill $source
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Skill> $targets
     * @throws StopActionException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function moveCertificationsAction(Skill $source, $targets)
    {
        $certRepo = $this->objectManager->get(CertificationRepository::class);
        $setGroup = count($targets) > 1;
        $certs = $certRepo->findBySkill($source);
        /** @var Certification $cert */
        foreach ($certs as $cert) {
            if (!$cert->getRequestGroup() && $setGroup) {
                $cert->setRequestGroup('skillSplit-' . time());
            }
            $newCert = $cert;
            /** @var Skill $target */
            foreach ($targets as $target) {
                $newCert->setSkill($target);
                $certRepo->add($newCert);
                $newCert = $cert->copy();
            }
        }
        $this->redirect('skillUpSplitting');
    }

    protected function prepareTreeSourceData(): void
    {
        $this->skillRepo->setDefaultOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $skills = $this->skillRepo->findAll();

        $brands = $this->brandRepository->findAllWithSkills();
        $paths = $this->skillPathRepository->findAll();

        $rewardRepo = $this->objectManager->get(RewardRepository::class);
        $rewardRepo->setDefaultOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $rewards = $rewardRepo->findAllBackend();

        $tagRepo = $this->objectManager->get(TagRepository::class);
        $tagRepo->setDefaultOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $tags = $tagRepo->findAll();

        $dataSources[] = [
            'label' => 'Rewards',
            'key' => 'r',
            'data' => $rewards,
        ];
        $dataSources[] = [
            'label' => 'SkillSets',
            'key' => 'p',
            'data' => $paths,
        ];
        $dataSources[] = [
            'label' => 'Brands',
            'key' => 'b',
            'data' => $brands,
        ];
        $dataSources[] = [
            'label' => 'Skills',
            'key' => 's',
            'data' => $skills,
        ];

        $highlightSources = $dataSources;
        $highlightSources[] = [
            'label' => 'Tags',
            'key' => 't',
            'data' => $tags,
        ];

        $this->view->assign('dataSources', $dataSources);
        $this->view->assign('highlightSources', $highlightSources);
    }

    public function reportingAction()
    {
        GeneralUtility::makeInstance(PageRenderer::class)->loadRequireJsModule("TYPO3/CMS/Skills/ReportingBackend");

        $this->view->assign('brands', $this->brandRepository->findAllWithMembers()->toArray());
        $this->view->assign('skillSets', $this->skillPathRepository->findAll()->toArray());
    }

    /**
     * @param array $brands
     * @param array $skillSets
     * @param string $dateFrom
     * @param string $dateTo
     */
    public function generateReportAction(array $brands, array $skillSets, string $dateFrom, string $dateTo)
    {
        $lines = [];

        $certRepo = $this->objectManager->get(CertificationRepository::class);
        $fromDate = $this->convertDate($dateFrom . ' 00:00:00');
        $toDate = $this->convertDate($dateTo . ' 23:59:59');

        $certifications = $certRepo->findByGrantDateAndBrandsAndSkillSets($fromDate, $toDate, $brands, $skillSets);

        /** @var Certification $certification */
        foreach ($certifications as $certification) {
            $skill = $certification->getSkill();
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
                    : ($certification->getCertifier()->getTestSystem() ? $certification->getCertifier()->getTestSystem() : 'deleted user'))
                    : 'CertoBot',
                $certification->getBrand() ? $certification->getBrand()->getName() : '',
                $certification->getCampaign() ? $certification->getCampaign()->getTitle() : '',
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
        ]);

        $filename = 'Verifications_' . date('YmdHi') . '.csv';

        CsvService::sendCSVFile($lines, $filename);
    }

    private function convertDate(string $date): ?\DateTime
    {
        $format = LocalizationUtility::translate('backend.date.date_format-presentation', 'Skills') . ' G:i:s';
        $date = \DateTime::createFromFormat($format, $date);
        return $date === false ? null : $date;
    }

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
        $response = new JsonResponse();
        $response->getBody()->write(json_encode($result));
        return $response;
    }

    public function ajaxAddSkill(ServerRequestInterface $request): ResponseInterface
    {
        $this->initializeAction();
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
        $response = new JsonResponse();
        $response->getBody()->write(json_encode($result));
        return $response;
    }

    public function ajaxAddLink(ServerRequestInterface $request): ResponseInterface
    {
        $this->initializeAction();
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
        $response = new JsonResponse();
        $response->getBody()->write(json_encode($result));
        return $response;
    }

    public function ajaxSetSkillDormant(ServerRequestInterface $request): ResponseInterface
    {
        return $this->deleteOrDormant('dormant', $request);
    }

    public function ajaxDeleteSkill(ServerRequestInterface $request): ResponseInterface
    {
        return $this->deleteOrDormant('delete', $request);
    }

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
            $response = new JsonResponse();
            $response->getBody()->write(json_encode($result));
            return $response;
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
            $rewardRepository = $this->objectManager->get(RewardRepository::class);
            /** @var Reward $reward */
            $reward = $rewardRepository->findByUid($rewardId);

            foreach ($reward->getPrerequisites() as $pre) {
                if ($pre->getSkill()->getUid() === $id) {
                    $reward->getPrerequisites()->detach($pre);
                    break;
                }
            }
            $rewardRepository->update($reward);
            $this->persistAll();

            $result['success'] = true;
        }
        $response = new JsonResponse();
        $response->getBody()->write(json_encode($result));
        return $response;
    }

    public function ajaxRemoveRequirement(ServerRequestInterface $request): ResponseInterface
    {
        $result = [
            'success' => false,
            'error' => '',
        ];

        $setSkillId = (int)($request->getQueryParams()['id'] ?? 0);
        if ($setSkillId) {
            $reqRepo = $this->objectManager->get(RequirementRepository::class);
            $requirement = $reqRepo->findBySetSkillId($setSkillId);
            $valid = false;
            $this->accessCheck = new BackendPageAccessCheckService();
            if ($this->accessCheck->writeAccess($requirement->getPid())) {
                $valid = true;
            }

            if ($valid && $requirement) {
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
                            $reqRepo->update($requirement);
                            if (!$sets->count()) {
                                $reqRepo->remove($requirement);
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
        $response = new JsonResponse();
        $response->getBody()->write(json_encode($result));
        return $response;
    }

    public function syllabusForSetAction(SkillPath $skillSet)
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
        exit();
    }

    public function syllabusAction(Reward $reward)
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
        exit();
    }

    public function completeDownloadAction(Reward $reward)
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
        exit();
    }

    public function completeDownloadForSetAction(SkillPath $skillSet)
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
        exit();
    }

    public function verifierPermissionsAction()
    {
        $certifierRepository = $this->objectManager->get(CertifierRepository::class);
        $verifierList = [];
        foreach ($certifierRepository->findAll()->toArray() as $certifier) {
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
    }

    /**
     * @param array $verifiers
     * @param array $skillSets
     * @param string $submitType
     * @param string $tier1
     * @param string $tier2
     * @param string $tier4
     * @throws StopActionException
     */
    public function modifyPermissionsAction(
        array $verifiers,
        array $skillSets,
        string $submitType,
        string $tier1,
        string $tier2,
        string $tier4
    )
    {
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

    private function getSkillsOfReward(int $rewardId): array
    {
        $skillIds = [];
        $rewardRepo = $this->objectManager->get(RewardRepository::class);
        /** @var Reward $reward */
        $reward = $rewardRepo->findByUid($rewardId);

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

    private function getSkillIdsByCombinedSource(string $combinedId): array
    {
        $id = (int)substr($combinedId, 1);
        switch ($combinedId[0]) {
            case 's':
                $skillIds = [$id];
                break;
            case 'b':
                $skillIds = $this->getSkillsOfBrand($id);
                break;
            case 'r':
                $skillIds = $this->getSkillsOfReward($id);
                break;
            case 't':
                $skillIds = $this->getSkillsByTag($id);
                break;
            case 'p':
                $skillIds = $this->getSkillsBySet($id);
                break;
            default:
                $skillIds = [];
        }
        return $skillIds;
    }

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
        };

        /** @var Skill $skill */
        $skill = $this->skillRepo->findByUid($skillId);

        if (!$skill || !$this->accessCheck->readAccess($skill->getPid())) {
            return;
        }

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_skills_domain_model_skill');
        $qb->getRestrictions()->removeAll();
        $qb
            ->select('skill.uid', 'setskill.uid as linkid', 'skill.title', 'skill.icon', 'setskill.skill',
                'skill.dormant', 'skill.description', 'skill.goals', 'skill.pid')
            ->from('tx_skills_domain_model_skill', 'skill')
            ->leftJoin('skill', 'tx_skills_domain_model_requirement', 'req', 'req.skill = skill.uid')
            ->leftJoin('req', 'tx_skills_domain_model_set', 'set', 'set.requirement = req.uid')
            ->leftJoin('set', 'tx_skills_domain_model_setskill', 'setskill', 'setskill.tx_set = set.uid')
            ->leftJoin('setskill', 'tx_skills_domain_model_skill', 'child', 'setskill.skill = child.uid')
            ->where('skill.uid = ' . $skillId, 'skill.deleted = 0', '(child.uid IS NULL or child.deleted = 0)');
        $children = $qb->execute()->fetchAll();

        if (empty($children)) {
            return;
        }

        foreach ($children as $key => $child) {
            if (!$this->accessCheck->readAccess((int)$child['pid'])) {
                unset($children[$key]);
            }
        }

        $icon = $children[0]['icon'];
        if (substr($icon, 0, 2) !== 'fa') {
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
    protected function getHref($controller, $action, $parameters = []): string
    {
        $uriBuilder = $this->objectManager->get(\TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder::class);
        $uriBuilder->setRequest($this->request);
        return $uriBuilder->reset()->uriFor($action, $parameters, $controller);
    }

    private static function translate(string $label): ?string
    {
        return LocalizationUtility::translate($label, 'skills');
    }

    private function deleteOrDormant(string $type, ServerRequestInterface $request): ResponseInterface
    {
        $result = [
            'success' => false,
        ];

        $id = (int)($request->getQueryParams()['id'] ?? 0);

        if (!$id) {
            $response = new JsonResponse();
            $response->getBody()->write(json_encode($result));
            return $response;
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
            $skill->setRequirements($this->objectManager->get(ObjectStorage::class));

            if ($type === 'dormant') {
                $skill->setDormant(new \DateTime());
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
        $response = new JsonResponse();
        $response->getBody()->write(json_encode($result));
        return $response;
    }

    protected function persistAll(): void
    {
        $this->objectManager->get(PersistenceManager::class)->persistAll();
    }
}
