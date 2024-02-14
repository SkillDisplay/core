<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Controller;

use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Mvc\View\JsonView;
use SkillDisplay\Skills\Service\UserOrganisationsService;

class SearchController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly SkillRepository $skillRepo,
        protected readonly SkillPathRepository $skillSetRepo,
        protected readonly CertificationRepository $verificationRepo,
        protected readonly BrandRepository $brandRepository
    ) {
        parent::__construct($userRepository);
    }

    public function searchAction(
        string $searchWord,
        bool $skillSearch = true,
        bool $skillSetSearch = true,
        bool $verificationSearch = true,
        bool $brandSearch = true
    ): ResponseInterface {
        $skills = [];
        $skillSets = [];
        $verifications = [];
        $brands = [];
        $message = '';
        $searchWord = trim($searchWord);

        if (mb_strlen($searchWord) > 2) {
            $user = $this->getCurrentUser();

            if ($skillSearch) {
                $skills = $this->skillRepo->findBySearchWord(
                    $searchWord,
                    UserOrganisationsService::getOrganisationsOrEmpty($user)
                );
                if ($user) {
                    foreach ($skills as $skill) {
                        $skill->setUserForCompletedChecks($user);
                    }
                }
            }
            if ($skillSetSearch) {
                $skillSets = $this->skillSetRepo->findBySearchWord(
                    $searchWord,
                    UserOrganisationsService::getOrganisationsOrEmpty($user)
                );
                if ($user) {
                    /** @var SkillPath $skillSet */
                    foreach ($skillSets as $skillSet) {
                        $skillSet->setUserForCompletedChecks($user);
                    }
                }
            }
            if ($brandSearch) {
                $brands = $this->brandRepository->findBySearchWord($searchWord);
            }
            if ($user && $verificationSearch) {
                $groups = $this->verificationRepo->findBySearchWord($searchWord, $user);
                foreach ($groups as $group) {
                    $group['certs'][0]->getSkill()?->setUserForCompletedChecks($user);
                }
                $verifications = CertificationController::convertGroupsToJson($groups);
            }
        } else {
            $message = 'Search phrase too short. At least three characters are required.';
        }

        $data = [
            'skills' => $skills,
            'skillSets' => $skillSets,
            'verifications' => $verifications,
            'brands' => $brands,
            'message' => $message,
        ];
        if ($this->view instanceof JsonView) {
            $configuration = [
                'skills' => ['_descendAll' => Skill::JsonViewConfiguration],
                'skillSets' => ['_descendAll' => SkillPath::JsonViewConfiguration],
                'verifications' => ['_descendAll' => Certification::JsonViewConfiguration],
                'brands' => ['_descendAll' => Brand::JsonViewMinimalConfiguration],
                'message' => [],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }

        $this->view->assignMultiple($data);
        return $this->createResponse();
    }
}
