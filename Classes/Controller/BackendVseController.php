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
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\RequirementRepository;
use SkillDisplay\Skills\Domain\Repository\RewardRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\TagRepository;
use SkillDisplay\Skills\Service\BackendPageAccessCheckService;
use SkillDisplay\Skills\Service\VerificationService;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

class BackendVseController extends BackendController
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
        protected readonly TagRepository $tagRepository,
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

    public function skillTreeAction(): ResponseInterface
    {
        return $this->generateOutput();
    }

    public function ajaxTreeSources(): ResponseInterface
    {
        $accessCheck = new BackendPageAccessCheckService();

        $this->skillRepo->setDefaultOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $skills = [];
        foreach ($this->skillRepo->findAll() as $skill) {
            /** @var Skill $skill */
            if ($accessCheck->readAccess($skill->getPid())) {
                $skills[] = $skill;
            }
        }

        $brands = [];
        foreach ($this->brandRepository->findAllWithSkills() as $brand) {
            if ($accessCheck->readAccess($brand->getPid())) {
                $brands[] = $brand;
            }
        }

        $paths = [];
        foreach ($this->skillPathRepository->findAll() as $path) {
            /** @var SkillPath $path */
            if ($accessCheck->readAccess($path->getPid())) {
                $paths[] = $path;
            }
        }

        $this->tagRepository->setDefaultOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $tags = $this->tagRepository->findAll();

        $data_with_title = function ($elem) {
            return [
                'uid' => $elem->getUid(),
                'name' => $elem->getTitle(),
            ];
        };

        $data_with_name = function ($elem) {
            return [
                'uid' => $elem->getUid(),
                'name' => $elem->getName(),
            ];
        };

        $dataSources[] = [
            'label' => 'SkillSets',
            'key' => 'p',
            'data' => array_map($data_with_name, $paths),
        ];
        $dataSources[] = [
            'label' => 'Brands',
            'key' => 'b',
            'data' => array_map($data_with_name, $brands),
        ];
        $dataSources[] = [
            'label' => 'Skills',
            'key' => 's',
            'data' => array_map($data_with_title, $skills),
        ];

        $highlightSources = $dataSources;
        $highlightSources[] = [
            'label' => 'Tags',
            'key' => 't',
            'data' => array_map($data_with_title, $tags->toArray()),
        ];

        $response_data = [
            'data_sources' => $dataSources,
            'highlight_sources' => $highlightSources,
        ];
        return new JsonResponse($response_data);
    }
}
