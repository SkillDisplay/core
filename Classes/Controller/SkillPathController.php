<?php
declare(strict_types=1);
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

use Doctrine\DBAL\Driver\Exception;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\RecommendedSkillSetRepository;
use SkillDisplay\Skills\Domain\Repository\RewardRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Seo\PageTitleProvider;
use SkillDisplay\Skills\Service\UserOrganisationsService;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\Service\TypoLinkCodecService;

class SkillPathController extends AbstractController
{
    protected SkillPathRepository $skillPathRepository;
    protected RecommendedSkillSetRepository $recommendedSkillSetRepository;

    public function __construct(
        SkillPathRepository $skillPathRepository,
        RecommendedSkillSetRepository $recommendedSkillSetRepository
    ) {
        $this->skillPathRepository = $skillPathRepository;
        $this->recommendedSkillSetRepository = $recommendedSkillSetRepository;
    }

    /**
     * @param bool $includeFullSkills
     * @param string $apiKey
     */
    public function listAction(bool $includeFullSkills = false, string $apiKey = '')
    {
        $user = $this->getCurrentUser(!$apiKey, $apiKey);
        $paths = $this->skillPathRepository->findAllVisible(UserOrganisationsService::getOrganisationsOrEmpty($user));

        if ($this->view instanceof JsonView) {
            $GLOBALS['reducedProgress'] = true;

            $onlyConfig = [
                'uid',
                'name',
                'description',
                'mediaPublicUrl',
                'links',
                'skillCount',
                'brand',
                'certificate',
                'legitimationDate',
                'firstCategoryTitle',
            ];
            $descendConfig = [
                'links' => [
                    '_descendAll' => [
                        '_only' => ['title', 'url'],
                    ],
                ],
                'brand' => Brand::JsonViewMinimalConfiguration,
            ];
            if ($includeFullSkills) {
                if ($user) {
                    /** @var SkillPath $path */
                    foreach ($paths as $path) {
                        $path->setUserForCompletedChecks($user);
                    }
                }
                $skillConfig = [
                    '_descendAll' => [
                        '_descend' => [
                            'progress' => [],
                        ],
                    ],
                ];
                $recommendedConfig = [
                    '_descendAll' => [
                        '_only' => [
                            'uid',
                            'title',
                            'progress',
                        ],
                        '_descend' => [
                            'progress' => [],
                        ],
                    ],
                ];
                $onlyConfig[] = 'skills';
                $onlyConfig[] = 'recommendedSkills';
                $descendConfig['skills'] = $skillConfig;
                $descendConfig['recommendedSkills'] = $recommendedConfig;
            }
            $configuration = [
                'paths' => [
                    '_descendAll' => [
                        '_only' => $onlyConfig,
                        '_descend' => $descendConfig,
                    ],
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        $this->view->assign('paths', $paths);
    }

    public function progressForSetAction(SkillPath $set)
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('');
        }
        if ($this->view instanceof JsonView) {
            $configuration = [
                'progressPercentage' => [],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        $set->setUserForCompletedChecks($user);
        $this->view->assign('progressPercentage', $set->getProgressPercentage());
    }

    public function listByBrandAction()
    {
        $sets = $this->skillPathRepository->findAllVisible(
            UserOrganisationsService::getOrganisationsOrEmpty($this->getCurrentUser())
        );
        $brands = [];
        /** @var SkillPath $set */
        foreach ($sets as $set) {
            /** @var Brand[] $brandsOfSet */
            $brandsOfSet = $set->getBrands()->toArray();
            if (!isset($brandsOfSet[0])) {
                continue;
            }
            $brands[$brandsOfSet[0]->getUid()][] = $set;
        }
        $this->view->assign('brands', $brands);
    }

    /**
     * @param SkillPath|null $set
     * @param bool $includeFullSkills
     * @param string $apiKey
     * @throws ImmediateResponseException
     * @throws PageNotFoundException
     * @throws Exception
     */
    public function showApiAction(SkillPath $set = null, bool $includeFullSkills = false, string $apiKey = '')
    {
        $this->assertEntityAvailable($set);
        $user = $this->getCurrentUser(false, $apiKey);
        if ($user) {
            $set->setUserForCompletedChecks($user);
            $set->setRecommendedSkillSets($this->recommendedSkillSetRepository->findBySkillSet($user, $set));
        }

        if (!UserOrganisationsService::isSkillPathVisibleForUser($set, $user)) {
            $this->response->setStatus(404);
            return;
        }

        if ($this->view instanceof JsonView) {
            if ($includeFullSkills) {
                $skillConfig = [
                    '_descendAll' => [
                        '_descend' => [
                            'progress' => [],
                            'dormant' => [],
                        ],
                    ],
                ];
            } else {
                $skillConfig = [
                    '_descendAll' => [
                        '_only' => [
                            'uid',
                            'title',
                            'progress',
                            'dormant',
                        ],
                        '_descend' => [
                            'progress' => [],
                            'dormant' => [],
                        ],
                    ],
                ];
            }

            $configuration = [
                'path' => [
                    '_only' => [
                        'uid',
                        'name',
                        'description',
                        'mediaPublicUrl',
                        'links',
                        'skills',
                        'skillCount',
                        'recommendedSkills',
                        'recommendedSkillSets',
                        'progress',
                        'progressPercentage',
                        'brand',
                        'certificate',
                        'firstCategoryTitle',
                    ],
                    '_descend' => [
                        'links' => [
                            '_descendAll' => [
                                '_only' => ['title', 'url'],
                            ],
                        ],
                        'skills' => $skillConfig,
                        'recommendedSkills' => [
                            '_descendAll' => [
                                '_only' => [
                                    'uid',
                                    'title',
                                    'progress',
                                ],
                                '_descend' => [
                                    'progress' => [],
                                ],
                            ],
                        ],
                        'recommendedSkillSets' => [
                            '_descendAll' => [
                                'sets' => [
                                    '_descendAll' => SkillPath::JsonRecommendedViewConfiguration,
                                ],
                            ],
                        ],
                        'progress' => [],
                        'progressPercentage' => [],
                        'brand' => Brand::JsonViewMinimalConfiguration,
                    ],
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        } else {
            GeneralUtility::makeInstance(PageTitleProvider::class)->setTitle($set->getName());
        }
        $this->view->assignMultiple([
            'user' => $user,
            'path' => $set,
        ]);
    }

    /**
     * @throws ImmediateResponseException
     * @throws PageNotFoundException
     */
    public function showAction(SkillPath $path = null)
    {
        $this->assertEntityAvailable($path);
        GeneralUtility::makeInstance(PageTitleProvider::class)->setTitle($path->getName());

        $this->view->assignMultiple([
            'path' => $path,
        ]);
    }

    public function getAwardsForSkillSetAction(SkillPath $set)
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('');
        }
        /** @var RewardRepository $rewardRepo */
        $rewardRepo = GeneralUtility::makeInstance(RewardRepository::class);
        $awards = $rewardRepo->getAllForSkillPath($user, $set);
        if ($this->view instanceof JsonView) {
            $configuration = [
                'awards' => [
                    '_descendAll' => Reward::ApiJsonViewConfiguration,
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
            $this->view->assign('awards', $awards);
        }
    }


    public function certificateDownloadAction(SkillPath $set)
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('');
        }
        $set->setUserForCompletedChecks($user);
        if ($set->getProgressPercentage()['tier1'] === 100) {
            if ($set->getCertificateLayoutFile()) {
                $typoLinkCodec = GeneralUtility::makeInstance(TypoLinkCodecService::class);
                $typolinkConfiguration = $typoLinkCodec->decode($set->getCertificateLink());

                preg_match('/.+=(\d+)/', $typolinkConfiguration['url'], $matches);
                $fileUid = $matches[1];
                try {
                    $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                    $templateFile = $resourceFactory->getFileObject($fileUid);
                    $layoutFile = $resourceFactory->getFileObject($set->getCertificateLayoutFile());

                    $pdfView = GeneralUtility::makeInstance(StandaloneView::class);
                    $pdfView->setTemplatePathAndFilename($layoutFile->getForLocalProcessing(false));
                    $pdfView->assign('pdfTemplate', $templateFile->getForLocalProcessing(false));
                    $pdfView->assign('user', $user);
                    $pdfView->assign('skillSet', $set);
                    $pdfView->render();
                    exit();
                } catch (FileDoesNotExistException $e) {
                }
            } else {
                // normal reward link
                $url = $this->getTSFE()->cObj->typoLink_URL(['parameter' => $set->getCertificateLink()]);
                HttpUtility::redirect(GeneralUtility::locationHeaderUrl($url));
            }
        } else {
            $url = $this->getTSFE()->cObj->typoLink_URL(['parameter' => $this->settings['pids']['root']]);
            HttpUtility::redirect(GeneralUtility::locationHeaderUrl($url));
        }
    }

    /**
     * @throws FileDoesNotExistException
     */
    public function syllabusForSetPdfAction(SkillPath $set)
    {
        if ($set->getVisibility() === SkillPath::VISIBILITY_ORGANISATION &&
            !UserOrganisationsService::isUserMemberOfOrganisations($set->getBrands(), $this->getCurrentUser())
        ) {
            exit();
        }
        $pdfView = GeneralUtility::makeInstance(StandaloneView::class);
        $pdfView->setTemplatePathAndFilename('EXT:skills/Resources/Private/PdfTemplates/SyllabusForSet.html');
        $skillPathRepo = GeneralUtility::makeInstance(SkillPathRepository::class);
        $skills = $skillPathRepo->getSkillsForSyllabusDownload($set);
        if ($set->getSyllabusLayoutFile()) {
            $templateFile = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject(
                $set->getSyllabusLayoutFile()
            );
            $pdfView->assign('pdfTemplate', $templateFile->getForLocalProcessing(false));
        }
        $pdfView->assign('skills', $skills);
        $pdfView->assign('set', $set);
        $pdfView->render();
        exit();
    }

    /**
     * @throws FileDoesNotExistException
     */
    public function completeDownloadForSetPdfAction(SkillPath $set)
    {
        if ($set->getVisibility() === SkillPath::VISIBILITY_ORGANISATION &&
            !UserOrganisationsService::isUserMemberOfOrganisations($set->getBrands(), $this->getCurrentUser())
        ) {
            exit();
        }
        $pdfView = GeneralUtility::makeInstance(StandaloneView::class);
        $pdfView->setTemplatePathAndFilename('EXT:skills/Resources/Private/PdfTemplates/FullDownloadSkillSet.html');
        $skillPathRepo = GeneralUtility::makeInstance(SkillPathRepository::class);
        $skills = $skillPathRepo->getSkillsForCompleteDownload($set);
        if ($set->getSyllabusLayoutFile()) {
            $templateFile = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject(
                $set->getSyllabusLayoutFile()
            );
            $pdfView->assign('pdfTemplate', $templateFile->getForLocalProcessing(false));
        }
        $pdfView->assign('skills', $skills);
        $pdfView->assign('set', $set);
        $pdfView->render();
        exit();
    }
}
