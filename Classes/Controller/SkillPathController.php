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

use Doctrine\DBAL\Driver\Exception;
use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Reward;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\RecommendedSkillSetRepository;
use SkillDisplay\Skills\Domain\Repository\RewardRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Seo\PageTitleProvider;
use SkillDisplay\Skills\Service\UserOrganisationsService;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\Service\TypoLinkCodecService;

class SkillPathController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly SkillPathRepository $skillSetRepository,
        protected readonly RecommendedSkillSetRepository $recommendedSkillSetRepository,
        protected readonly RewardRepository $rewardRepository,
    ) {
        parent::__construct($userRepository);
    }

    /**
     * @param bool $includeFullSkills
     * @param string $apiKey
     * @return ResponseInterface
     */
    public function listAction(bool $includeFullSkills = false, string $apiKey = ''): ResponseInterface
    {
        $user = $this->getCurrentUser(!$apiKey, $apiKey);
        $paths = $this->skillSetRepository->findAllVisible(UserOrganisationsService::getOrganisationsOrEmpty($user));

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
                'tags',
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
        return $this->createResponse();
    }

    public function progressForSetAction(SkillPath $set, string $apiKey = ''): ResponseInterface
    {
        $user = $this->getCurrentUser(false, $apiKey);
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
        return $this->createResponse();
    }

    public function listByBrandAction(): ResponseInterface
    {
        $sets = $this->skillSetRepository->findAllVisible(
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
        return $this->createResponse();
    }

    /**
     * @param SkillPath|null $set
     * @param bool $includeFullSkills
     * @param string $apiKey
     * @return ResponseInterface
     * @throws Exception
     * @throws PageNotFoundException
     * @throws PropagateResponseException
     */
    public function showApiAction(SkillPath $set = null, bool $includeFullSkills = false, string $apiKey = ''): ResponseInterface
    {
        $this->assertEntityAvailable($set);
        $user = $this->getCurrentUser(false, $apiKey);
        if ($user) {
            $set->setUserForCompletedChecks($user);
            $set->setRecommendedSkillSets($this->recommendedSkillSetRepository->findBySkillSet($user, $set));
        }

        if (!UserOrganisationsService::isSkillPathVisibleForUser($set, $user)) {
            return $this->htmlResponse('')->withStatus(404);
        }

        if ($this->view instanceof JsonView) {
            if ($includeFullSkills) {
                $skillConfig = [
                    '_descendAll' => [
                        '_descend' => [
                            'progress' => [],
                            'dormant' => [],
                            'domainTag' => [
                                '_only' => [
                                    'uid',
                                    'title',
                                ],
                            ],
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
                            'domainTag' => [
                                '_only' => [
                                    'uid',
                                    'title',
                                ],
                            ],
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
                        'tags',
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
                        'tags' => [
                            '_descendAll' => [
                                '_only' => [
                                    'uid',
                                    'title',
                                ],
                            ],
                        ],
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
        return $this->createResponse();
    }

    /**
     * @throws PropagateResponseException
     * @throws PageNotFoundException
     */
    public function showAction(SkillPath $path = null): ResponseInterface
    {
        $this->assertEntityAvailable($path);
        GeneralUtility::makeInstance(PageTitleProvider::class)->setTitle($path->getName());

        $this->view->assignMultiple([
            'path' => $path,
        ]);
        return $this->createResponse();
    }

    public function getAwardsForSkillSetAction(SkillPath $set): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('');
        }
        $awards = $this->rewardRepository->getAllForSkillPath($user, $set);
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
        return $this->createResponse();
    }

    public function certificateDownloadAction(SkillPath $set): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('');
        }
        $set->setUserForCompletedChecks($user);
        if ($set->getProgressPercentage()['tier1'] === 100.0) {
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
                } catch (FileDoesNotExistException) {
                    $url = $this->getTSFE()->cObj->typoLink_URL(['parameter' => $this->settings['pids']['root']]);
                }
            } else {
                // normal reward link
                $url = $this->getTSFE()->cObj->typoLink_URL(['parameter' => $set->getCertificateLink()]);
            }
        } else {
            $url = $this->getTSFE()->cObj->typoLink_URL(['parameter' => $this->settings['pids']['root']]);
        }
        return new RedirectResponse($this->addBaseUriIfNecessary($url), 303);
    }

    /**
     * @throws FileDoesNotExistException
     */
    public function syllabusForSetPdfAction(SkillPath $set): never
    {
        if ($set->getVisibility() === SkillPath::VISIBILITY_ORGANISATION &&
            !UserOrganisationsService::isUserMemberOfOrganisations($set->getBrands(), $this->getCurrentUser())
        ) {
            exit();
        }
        $pdfView = GeneralUtility::makeInstance(StandaloneView::class);
        $pdfView->setTemplatePathAndFilename('EXT:skills/Resources/Private/PdfTemplates/SyllabusForSet.html');
        $skills = $this->skillSetRepository->getSkillsForSyllabusDownload($set);
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
    public function completeDownloadForSetPdfAction(SkillPath $set): void
    {
        if ($set->getVisibility() === SkillPath::VISIBILITY_ORGANISATION &&
            !UserOrganisationsService::isUserMemberOfOrganisations($set->getBrands(), $this->getCurrentUser())
        ) {
            throw new AuthenticationException('');
        }
        $pdfView = GeneralUtility::makeInstance(StandaloneView::class);
        $pdfView->setTemplatePathAndFilename('EXT:skills/Resources/Private/PdfTemplates/FullDownloadSkillSet.html');
        $skills = $this->skillSetRepository->getSkillsForCompleteDownload($set);
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
