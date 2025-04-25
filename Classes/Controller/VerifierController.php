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
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\VerificationService;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class VerifierController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly CertifierRepository $certifierRepository,
        protected readonly VerificationService $verificationService,
        protected readonly CertificationRepository $certificationRepository,
        protected readonly BrandRepository $brandRepository
    ) {
        parent::__construct($userRepository);
    }

    public function showAction(Certifier $verifier): ResponseInterface
    {
        if ($this->view instanceof JsonView) {
            $configuration = [
                'verifier' => Certifier::JsonViewConfiguration,
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
            $verifier = $verifier->toJsonData(true);
        }

        $this->verificationService->setCreditSettings($this->settings['credits']);
        foreach ($verifier['recentRequests'] as &$request) {
            if ($request['requestGroup']) {
                $certs = $this->certificationRepository->findByRequestGroup($request['requestGroup'])->toArray();
            } else {
                $certs = [$this->certificationRepository->findByUid($request['uid'])];
            }
            $neededPoints = $this->verificationService->calculatePointsNeeded($certs);
            /** @var Brand $organisation */
            $organisation = $this->brandRepository->findByUid((int)$request['brandId']);
            $request['canBeAccepted'] = $certs[0]->isPending() && ($organisation->getCreditOverdraw() ||
                          $this->verificationService->organisationHasEnoughCredit($organisation->getUid(), $neededPoints));
        }
        $this->view->assign('verifier', $verifier);
        return $this->createResponse();
    }

    /**
     * @param int $tier
     * @param Skill|null $skill
     * @param SkillPath|null $set
     * @return ResponseInterface
     */
    public function forSkillAction(int $tier, ?Skill $skill, ?SkillPath $set): ResponseInterface
    {
        if ($this->view instanceof JsonView) {
            $configuration = [
                'verifiers' => [
                    '_descendAll' => [
                        Certifier::JsonViewConfiguration,
                    ],
                ],
                'testSystems' => [
                    '_descendAll' => [
                        Certifier::JsonViewConfiguration,
                    ],
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }

        $user = $this->getCurrentUser(false);
        if (!$user || $tier < 1 || $tier > 4 || $tier === 3) {
            $this->view->assign('verifiers', []);
            $this->view->assign('testSystems', []);
            return $this->createResponse();
        }

        $skills = $skill ? [$skill] : [];
        if ($set) {
            $skills = $set->getSkills()->toArray();
        }

        $userSelectedVerifiers = array_map(
            fn(Certifier $fav) => $fav->getUid(),
            $user->getFavouriteCertifiers()->getArray()
        );

        $favouriteVerifiers = [];
        $convertedPersonVerifiers = [];
        $convertedTestVerifiers = [];
        $verifiers = $this->verificationService->getVerifiersForSkills($skills, $user, $tier);
        foreach ($verifiers as $verifier) {
            if ($verifier->getUser() !== null) {
                // disallow self-verification
                if ($verifier->getUser()->getUid() === $user->getUid()) {
                    continue;
                }
                $verifierJson = $verifier->toJsonData();
                if (in_array($verifier->getUid(), $userSelectedVerifiers, true)) {
                    $verifierJson['favourite'] = true;
                    $favouriteVerifiers[] = $verifierJson;
                } else {
                    $convertedPersonVerifiers[] = $verifierJson;
                }
            } else {
                $verifierJson = $verifier->toJsonData();
                $convertedTestVerifiers[] = $verifierJson;
            }

        }
        $convertedPersonVerifiers = array_merge($favouriteVerifiers, $convertedPersonVerifiers);

        $this->view->assign('verifiers', $convertedPersonVerifiers);
        $this->view->assign('testSystems', $convertedTestVerifiers);
        return $this->createResponse();
    }

    public function listOfUserAction(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            throw new AuthenticationException('', 7742607010);
        }
        $verifiers = $this->certifierRepository->findByUser($user);

        if ($this->view instanceof JsonView) {
            $this->verificationService->setCreditSettings($this->settings['credits']);

            $convertedVerifiers = [];
            /** @var Certifier $verifier */
            foreach ($verifiers as $verifier) {
                $verifierJson = $verifier->toJsonData(true);

                foreach ($verifierJson['recentRequests'] as &$request) {
                    if ($request['requestGroup']) {
                        $certs = $this->certificationRepository->findByRequestGroup($request['requestGroup'])->toArray();
                    } else {
                        $certs = [$this->certificationRepository->findByUid($request['uid'])];
                    }
                    $neededPoints = $this->verificationService->calculatePointsNeeded($certs);
                    /** @var Brand $organisation */
                    $organisation = $this->brandRepository->findByUid((int)$request['brandId']);
                    $request['canBeAccepted'] = $certs[0]->isPending() && ($organisation->getCreditOverdraw() ||
                                                $this->verificationService->organisationHasEnoughCredit($organisation->getUid(), $neededPoints));
                }
                unset($request);

                $convertedVerifiers[] = $verifierJson;
            }
            $verifiers = $convertedVerifiers;

            $configuration = [
                'verifiers' => [
                    '_descendAll' => Certifier::JsonViewConfiguration,
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }

        $this->view->assign('verifiers', $verifiers);
        return $this->createResponse();
    }
}
