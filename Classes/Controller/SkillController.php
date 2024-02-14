<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein <markus.klein@reelworx.at>, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Controller;

use Doctrine\DBAL\Driver\Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Campaign;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillGroup;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\RecommendedSkillSetRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Seo\PageTitleProvider;
use SkillDisplay\Skills\Service\UserOrganisationsService;
use SkillDisplay\Skills\Service\VerificationService;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class SkillController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly SkillRepository $skillRepository,
        protected readonly RecommendedSkillSetRepository $recommendedSkillSetRepository,
        protected readonly VerificationService $verificationService,
    ) {
        parent::__construct($userRepository);
    }

    /**
     * @param Skill|null $skill
     * @param SkillPath|null $path
     * @param string $apiKey
     * @return ResponseInterface
     * @throws PageNotFoundException
     * @throws PropagateResponseException
     * @throws Exception
     */
    public function showAction(Skill $skill = null, SkillPath $path = null, string $apiKey = ''): ResponseInterface
    {
        $this->assertEntityAvailable($skill);
        $user = $this->getCurrentUser(false, $apiKey);

        if (!UserOrganisationsService::isSkillVisibleForUser($skill, $user)) {
            return $this->htmlResponse('')->withStatus(404);
        }

        if ($user) {
            $skill->setUserForCompletedChecks($user);
            $skill->setRecommendedSkillSets($this->recommendedSkillSetRepository->findBySkill($user, $skill));
        }

        $this->view->assign('skill', $skill);

        if ($this->view instanceof JsonView) {
            $skillConfiguration = Skill::JsonViewConfiguration;
            $skillConfiguration['_only'][] = 'recommendedSkillSets';
            $skillConfiguration['_descend']['recommendedSkillSets'] = [
                '_descendAll' => [
                    'sets' => [
                        '_descendAll' => SkillPath::JsonRecommendedViewConfiguration,
                    ],
                ],
            ];
            $configuration = [
                'skill' => $skillConfiguration,
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        } else {
            GeneralUtility::makeInstance(PageTitleProvider::class)->setTitle($skill->getTitle());
            $this->view->assignMultiple([
                'user' => $user,
                'path' => $path,
            ]);
        }
        return $this->createResponse();
    }

    /**
     * @param Skill|null $skill
     * @param SkillPath|null $path
     * @param SkillGroup|null $group
     * @param Campaign|null $campaign
     * @param Certifier|null $certifier
     * @param string $comment
     * @return ResponseInterface
     * @throws NoSuchArgumentException
     */
    public function skillUpAjaxAction(
        Skill $skill = null,
        SkillPath $path = null,
        SkillGroup $group = null,
        Campaign $campaign = null,
        Certifier $certifier = null,
        string $comment = ''
    ): ResponseInterface {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('');
        }

        if (!$skill && !$path && !$group) {
            throw new InvalidArgumentException('Missing entity to apply skillup to');
        }
        $tier = (int)$this->request->getArgument('tier');

        $this->verificationService->setCreditSettings($this->settings['credits']);

        $html = '';
        $redirect = '';
        $success = true;

        // check a verifier has been selected if it's no self skillup
        if (!$certifier && $tier !== 3) {
            $success = false;
            $html = 'No verifier has been selected';
        } elseif ($skill) {
            $result = $this->verificationService->handleSkillUpRequest(
                [$skill],
                '',
                $user,
                $tier,
                $comment,
                $certifier,
                $campaign,
                false
            );
            if ($result['errorMessage']) {
                $html = htmlspecialchars($result['errorMessage'] .
                                         ' ' .
                                         $result['failedSkills'][$skill->getUid()]['reason']);
                $success = false;
            } else {
                $redirect = $result['redirectUrl'];
            }
        } elseif ($path || $group) {
            $skills = $path ? $path->getSkills() : $group->getSkills();
            $groupId = $path ? $path->getSkillGroupId() : $group->getSkillGroupId();
            $result = $this->verificationService->handleSkillUpRequest(
                $skills->toArray(),
                $groupId,
                $user,
                $tier,
                $comment,
                $certifier,
                $campaign,
                false
            );
            if ($result['errorMessage']) {
                $success = false;
                $html = $result['errorMessage'] . ' ' . implode(',', array_keys($result['failedSkills']));
            } else {
                $redirect = $result['redirectUrl'];
            }
        } else {
            $success = false;
            $html = 'Error';
        }

        /** @var JsonView $view */
        $view = $this->view;
        $view->setVariablesToRender(['html', 'success', 'redirect']);
        $view->assign('success', $success);
        $view->assign('redirect', $redirect);
        $view->assign('html', $html);
        return $this->createResponse();
    }

    public function progressIndicatorAction(): ResponseInterface
    {
        $percent = GeneralUtility::intExplode(',', $this->settings['defaultPercentage']);
        $progress = [
            'tier3' => $percent[0],
            'tier2' => $percent[1],
            'tier1' => $percent[2],
            'tier4' => $percent[3],
        ];
        $this->view->assign('progress', $progress);
        return $this->createResponse();
    }
}
