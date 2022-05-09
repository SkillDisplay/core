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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\TagRepository;
use SkillDisplay\Skills\Service\BackendPageAccessCheckService;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

class BackendVseController extends BackendController
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

    public function skillTreeAction()
    {
    }

    public function ajaxTreeSources(ServerRequestInterface $request): ResponseInterface
    {
        $accessCheck = new BackendPageAccessCheckService();

        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $skillRepo = $objectManager->get(SkillRepository::class);
        $skillRepo->setDefaultOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $skills = [];
        foreach ($skillRepo->findAll() as $skill) {
            /** @var Skill $skill */
            if ($accessCheck->readAccess($skill->getPid())) {
                $skills[] = $skill;
            }
        }

        $brandRepo = $objectManager->get(BrandRepository::class);
        $brands = [];
        foreach ($brandRepo->findAllWithSkills() as $brand) {
            /** @var Brand $brand */
            if ($accessCheck->readAccess($brand->getPid())) {
                $brands[] = $brand;
            }
        }

        $pathRepo = $objectManager->get(SkillPathRepository::class);
        $paths = [];
        foreach ($pathRepo->findAll() as $path) {
            /** @var SkillPath $path */
            if ($accessCheck->readAccess($path->getPid())) {
                $paths[] = $path;
            }
        }

        $tagRepo = $objectManager->get(TagRepository::class);
        $tagRepo->setDefaultOrderings(['title' => QueryInterface::ORDER_ASCENDING]);
        $tags = $tagRepo->findAll();

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
        $response = new JsonResponse();
        $response->getBody()->write(json_encode($response_data));
        return $response;
    }
}
