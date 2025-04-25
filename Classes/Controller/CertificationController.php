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
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Campaign;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\VerificationCreditUsage;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CampaignRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditPackRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditUsageRepository;
use SkillDisplay\Skills\Service\VerificationService;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;

class CertificationController extends AbstractController
{
    public function __construct(
        UserRepository $userRepository,
        protected readonly CertifierRepository $certifierRepository,
        protected readonly CertificationRepository $certificationRepository,
        protected readonly BrandRepository $brandRepository,
        protected readonly VerificationCreditPackRepository $verificationCreditPackRepository,
        protected readonly VerificationCreditUsageRepository $verificationCreditUsageRepository,
        protected readonly SkillPathRepository $skillSetRepository,
        protected readonly SkillRepository $skillRepository,
        protected readonly CampaignRepository $campaignRepository,
        protected readonly VerificationService $verificationService
    ) {
        parent::__construct($userRepository);
    }

    public function showAction(Certification $verification): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('', 8903810176);
        }
        if ($this->view instanceof JsonView) {
            $configuration = [
                'verification' => Certification::JsonViewConfiguration,
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }

        $verificationOwner = $verification->getUser();
        $userIsVerifier = $verification->getCertifier() && $user === $verification->getCertifier()->getUser();

        $verificationJson = $verification->toJsonData($userIsVerifier, !$userIsVerifier && $user !== $verificationOwner);
        $group = $verification->getRequestGroup();
        if ($group) {
            $certs = $this->certificationRepository->findByRequestGroup($group)->toArray();
            /** @var Certification $cert */
            foreach ($certs as $cert) {
                $skill = $cert->getSkill();
                if ($skill) {
                    $skill->setUserForCompletedChecks($verificationOwner);
                    $verificationJson['skills'][] = $skill;
                }
            }
        } else {
            $certs = [$verification];
            $verification->getSkill()->setUserForCompletedChecks($verificationOwner);
            $verificationJson['skills'][] = $verification->getSkill();
        }
        if ($userIsVerifier) {
            $this->verificationService->setCreditSettings($this->settings['credits']);
            $neededPoints = $this->verificationService->calculatePointsNeeded($certs);
            $organisation = $verification->getBrand();
            $acceptable = $verification->isPending() && ($organisation->getCreditOverdraw()
                    || $this->verificationService->organisationHasEnoughCredit($organisation->getUid(), $neededPoints));
            $verificationJson['canBeAccepted'] = $acceptable;
            $verificationJson['credits'] = $neededPoints;
            $verificationJson['price'] = (float)$this->settings['credits']['price'];
            $verificationJson['verifier']['isOrgaManager'] = in_array($organisation, $user->getManagedBrands()->toArray());
        }
        $verification = $verificationJson;

        $this->view->assign('verification', $verification);
        return $this->createResponse();
    }

    /**
     * note: no type annotation for $verifications since it can be array|ObjectStorage but extbase can't deal with it
     *
     * @param array $verifications
     * @param bool $accept
     * @param bool $decline
     * @param bool $revoke
     * @param string $reason
     * @return ResponseInterface
     */
    public function modifyAction(
        $verifications,
        bool $accept = false,
        bool $decline = false,
        bool $revoke = false,
        string $reason = ''
    ): ResponseInterface {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('', 7108249656);
        }

        if (!($accept xor $decline xor $revoke)) {
            throw new InvalidArgumentException('Wrong arguments. You need to define exactly 1 action.', 7106791197);
        }

        $this->verificationService->setCreditSettings($this->settings['credits']);

        $success = true;
        $errorMessage = '';

        /** @var Certification[] $certObjects */
        $certObjects = [];
        $verification = null;
        foreach ($verifications as $verificationId) {
            /** @var ?Certification $verification */
            $verification = $this->certificationRepository->findByUid($verificationId);
            if (!$verification) {
                $errorMessage = 'Invalid verification ID ' . $verificationId;
                $success = false;
                break;
            }
            // self verifications can only be modified by the owner, others by the verifier
            $userToCompare = $verification->getLevelNumber() === Skill::LevelTierMap['self']
                ? $verification->getUser()
                : $verification->getCertifier()?->getUser();
            if ($userToCompare && $userToCompare->getUid() === $user->getUid()) {
                $certObjects[] = $verification;
            } else {
                $errorMessage = 'Access violation. The current user must be the verifier for verification ID ' . $verificationId;
                $success = false;
                break;
            }
        }
        if (empty($certObjects)) {
            $success = false;
            $errorMessage = 'None of the given verifications is applicable.';
        }
        if ($accept && $success && $verification) {
            $totalCreditPointsNeeded = $this->verificationService->calculatePointsNeeded($certObjects);
            $organisation = $verification->getCertifier()->getBrand();
            if (!$this->verificationService->organisationHasEnoughCredit($organisation->getUid(), $totalCreditPointsNeeded) && !$organisation->getCreditOverdraw()) {
                $errorMessage = 'Verification credit points are insufficient. Needed points: ' . $totalCreditPointsNeeded;
                $success = false;
            }
        }
        if ($success) {
            foreach ($certObjects as $verification) {
                $this->verificationService->confirmSkillUp($verification, $accept, $decline, $revoke, $reason);
            }
        }

        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['success', 'error']);
            $this->view->assign('error', $errorMessage);
            $this->view->assign('success', $success);
            return $this->createResponse();
        }
        return $this->htmlResponse('');
    }

    public function userCancelAction(array $verifications): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('', 8325379366);
        }
        $success = true;
        foreach ($verifications as $certificationId) {
            /** @var ?Certification $certification */
            $certification = $this->certificationRepository->findByUid($certificationId);
            if ($certification) {
                if ($certification->getUser()->getUid() === $user->getUid()) {
                    $this->verificationService->cancelSkillupRequest($certification);
                } else {
                    $success = false;
                    break;
                }
            } else {
                throw new InvalidArgumentException('Given certification id is invalid:' . $certificationId, 3420227456);
            }
        }

        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['success', 'dummy']);
            $this->view->assign('success', $success);
            return $this->createResponse();
        }
        return $this->htmlResponse('');
    }

    public function recentAction(): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if ($user) {
            $verifications = array_merge($user->getAcceptedCertifications(), $user->getPendingCertifications());
            //sort newest first
            usort($verifications, function (array $a, array $b) {
                /** @var Certification $certA */
                $certA = $a['certs'][0];
                /** @var Certification $certB */
                $certB = $b['certs'][0];
                return $certB->getCrdate() - $certA->getCrdate();
            });
            array_splice($verifications, 5); // only keep 15 items
            $verifications = static::convertGroupsToJson($verifications);
        } else {
            $verifications = [];
        }
        if ($this->view instanceof JsonView) {
            $configuration = [
                'verifications' => [
                    '_descendAll' => Certification::JsonViewConfiguration,
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        $this->view->assign('verifications', $verifications);
        return $this->createResponse();
    }

    public function recentRequestsAction(): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('', 3543270713);
        }
        $groups = $this->certificationRepository->findPendingByCertifierUser($user, 5);

        $verifications = static::convertGroupsToJson($groups);

        if ($this->view instanceof JsonView) {
            $configuration = [
                'verifications' => [
                    '_descendAll' => Certification::JsonViewConfiguration,
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }
        $this->view->assign('verifications', $verifications);
        return $this->createResponse();
    }

    public function listForVerifierAction(Certifier $verifier): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('', 4155755648);
        }
        $verifications = [];

        if ($verifier->getUser() && $verifier->getUser()->getUid() === $user->getUid()) {
            $groups = $this->certificationRepository->findByCertifier($verifier);
            $verifications = static::convertGroupsToJson($groups);
            $this->verificationService->setCreditSettings($this->settings['credits']);

            foreach ($verifications as &$request) {
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
            if ($this->view instanceof JsonView) {
                $configuration = [
                    'verifications' => [
                        '_descendAll' => Certification::JsonViewConfiguration,
                    ],
                ];
                $this->view->setVariablesToRender(array_keys($configuration));
                $this->view->setConfiguration($configuration);
            }
        }

        $this->view->assign('verifications', $verifications);
        return $this->createResponse();
    }

    public function listForOrganisationAction(Brand $organisation): ResponseInterface
    {
        $user = $this->getCurrentUser(false);
        if (!$user || !$user->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('', 2694705601);
        }
        $groups = $this->certificationRepository->findAcceptedByOrganisation($organisation, new DateTime('@0'), new DateTime());
        $usages = [];
        foreach ($groups as $group) {
            $points = 0;
            $price = 0;
            $fullySettled = false;
            $usedCredit = false;
            $billingDate = new DateTime();
            /** @var Certification $cert */
            foreach ($group['certs'] as $cert) {
                $points += $cert->getPoints();
                $price += $cert->getPrice();
            }
            if (isset($cert)) {
                $billingDate = $cert->getGrantDate();
                $creditUsages = $this->verificationCreditUsageRepository->findByVerification($cert)->toArray();
                if (count($creditUsages) > 0) {
                    $fullySettled = true;
                    /** @var VerificationCreditUsage $usage */
                    foreach ($creditUsages as $usage) {
                        $pack = $usage->getCreditPack();
                        if ($pack->getInitialPoints() > 0) {
                            $usedCredit = true;
                        } else {
                            $billingDate = $pack->getValuta();
                        }
                    }
                }
            }
            $jsonData = $cert->toJsonData(true);
            $jsonData['skillCount'] = count($group['certs']);
            $jsonData['credits'] = $points;
            $jsonData['price'] = $price;
            $jsonData['fullySettled'] = $fullySettled;
            $jsonData['usedCredit'] = $usedCredit;
            $jsonData['billingDate'] = ($fullySettled || $usedCredit) ? $billingDate->getTimestamp() : 0;
            $usages[] = $jsonData;
        }
        if ($this->view instanceof JsonView) {
            $configuration = [
                'usages' => [
                    '_descendAll' => Certification::JsonViewConfiguration,
                ],
            ];
            $this->view->setConfiguration($configuration);
            $this->view->setVariablesToRender(array_keys($configuration));
        }
        $this->view->assign('usages', $usages);
        return $this->createResponse();
    }

    public function historyAction(string $apiKey = ''): ResponseInterface
    {
        $user = $this->getCurrentUser(false, $apiKey);
        if (!$user) {
            throw new AuthenticationException('', 5955638498);
        }
        $groups = $this->certificationRepository->findByUser($user);
        $verifications = static::convertGroupsToJson($groups);

        if ($this->view instanceof JsonView) {
            $configuration = [
                'verifications' => [
                    '_descendAll' => Certification::JsonViewConfiguration,
                ],
            ];
            $this->view->setVariablesToRender(array_keys($configuration));
            $this->view->setConfiguration($configuration);
        }

        $this->view->assign('verifications', $verifications);
        return $this->createResponse();
    }

    public function createAction(string $apiKey = ''): ResponseInterface
    {
        $response = [
            'Version' => '1.0',
            'ErrorMessage' => '',
            'Verifications' => [],
        ];
        if ($this->view instanceof JsonView) {
            $configuration = [
                'Verifications' => [
                    '_descendAll' => Certification::JsonViewConfiguration,
                ],
            ];
            $this->view->setVariablesToRender(['Version', 'ErrorMessage', 'Verifications']);
            $this->view->setConfiguration($configuration);
        }

        $postData = file_get_contents('php://input');
        if ($postData === '') {
            $response['ErrorMessage'] = 'No data sent';
            $this->view->assignMultiple($response);
            return $this->createResponse();
        }

        $data = json_decode($postData, true);
        $signatureFromClient = $data['Signature'] ?? '';
        $level = $data['Level'] ?? '';
        $skillId = (int)($data['SkillId'] ?? 0);
        $skillSetId = (int)($data['SkillSetId'] ?? 0);
        $verifierId = (int)($data['VerifierId'] ?? 0);
        $username = $data['Username'] ?? '';
        $autoConfirm = $data['AutoConfirm'] ?? false;
        $campaignId = $data['CampaignId'] ?? null;
        $tier = Skill::LevelTierMap[$level] ?? 0;

        $userOfRequest = $this->getCurrentUser(false, $apiKey);

        $isUserByApiKey = $userOfRequest && $apiKey;
        $isSelfVerification = $level === 'self';
        $autoConfirm = $autoConfirm && !$isSelfVerification && $isUserByApiKey;
        $isSignatureRequired = $autoConfirm || $isUserByApiKey;

        $errorMessage = '';
        if (!$userOfRequest) {
            $errorMessage = 'Invalid API key or missing login';
        } elseif (!$tier) {
            $errorMessage = 'Invalid level';
        } elseif ($skillId === 0 && $skillSetId === 0) {
            $errorMessage = 'No skill or skillset given';
        } elseif ($isUserByApiKey && $username === '') {
            $errorMessage = 'No username';
        } elseif ($isSignatureRequired && $signatureFromClient === '') {
            $errorMessage = 'Missing signature';
        } elseif (!$isSelfVerification && $verifierId === 0) {
            $errorMessage = 'Missing verifierId';
        } else {
            /** @var ?Certifier $verifier */
            $verifier = $verifierId ? $this->certifierRepository->findByUid($verifierId) : null;
            // we never allow a different user logged-in users
            $user = $isUserByApiKey ? $this->userRepository->findByUsername($username) : $userOfRequest;
            /** @var ?Campaign $campaign */
            $campaign = $campaignId ? $this->campaignRepository->findByUid($campaignId) : null;

            if ($verifierId && !$verifier) {
                $errorMessage = 'Invalid verifier';
            } elseif (!$user) {
                $errorMessage = 'Invalid username';
            } elseif ($campaignId && !$campaign) {
                $errorMessage = 'Invalid campaign';
            } elseif ($isSignatureRequired) {
                $secret = $verifier->getSharedApiSecret();
                if ($secret === '') {
                    $errorMessage = 'Verifier has no shared secret';
                } else {
                    $data['Signature'] = '';
                    $signature = hash('sha256', json_encode($data) . $secret);
                    $signatureOk = hash_equals($signature, $signatureFromClient);
                    if (!$signatureOk) {
                        $errorMessage = 'Invalid signature';
                    }
                }
            }
        }

        if ($errorMessage) {
            $response['ErrorMessage'] = $errorMessage;
            $this->view->assignMultiple($response);
            return $this->createResponse();
        }

        $this->verificationService->setCreditSettings($this->settings['credits']);
        if ($skillId) {
            /** @var ?Skill $skill */
            $skill = $this->skillRepository->findByUid($skillId);
            if (!$skill) {
                $response['ErrorMessage'] = 'Invalid skillId';
                $this->view->assignMultiple($response);
                return $this->createResponse();
            }
            if ($tier !== Skill::LevelTierMap['self']) {
                $allowedVerifiers = $this->verificationService->getVerifiersForSkills([$skill], $user, $tier);
                $verifierHasPermissions = false;
                foreach ($allowedVerifiers as $v) {
                    if ($v->getUid() === $verifier->getUid()) {
                        $verifierHasPermissions = true;
                        break;
                    }
                }
                if (!$verifierHasPermissions) {
                    $response['ErrorMessage'] = 'Verifier not allowed for these skills';
                    $this->view->assignMultiple($response);
                    return $this->createResponse();
                }
            }
            $result = $this->verificationService->handleSkillUpRequest([$skill], '', $user, $tier, '', $verifier, $campaign, $autoConfirm);
            if ($result['errorMessage']) {
                $response['ErrorMessage'] = $result['errorMessage'] . ' ' .
                                            $result['failedSkills'][$skill->getUid()]['reason'];
            } else {
                $response['Verifications'] = $result['verifications'];
            }
        } elseif ($skillSetId) {
            /** @var ?SkillPath $skillSet */
            $skillSet = $this->skillSetRepository->findByUid($skillSetId);
            if (!$skillSet) {
                $response['ErrorMessage'] = 'Invalid skillSetId';
                $this->view->assignMultiple($response);
                return $this->createResponse();
            }
            $skills = $skillSet->getSkills()->toArray();
            if ($tier !== 3) {
                $allowedVerifiers = $this->verificationService->getVerifiersForSkills($skills, $user, $tier);
                $verifierHasPermissions = false;
                foreach ($allowedVerifiers as $v) {
                    if ($v->getUid() === $verifier->getUid()) {
                        $verifierHasPermissions = true;
                        break;
                    }
                }
                if (!$verifierHasPermissions) {
                    $response['ErrorMessage'] = 'Verifier not allowed for these skills';
                    $this->view->assignMultiple($response);
                    return $this->createResponse();
                }
            }
            $result = $this->verificationService->handleSkillUpRequest(
                $skills,
                $skillSet->getSkillGroupId(),
                $user,
                $tier,
                '',
                $verifier,
                $campaign,
                $autoConfirm
            );
            if ($result['errorMessage']) {
                $response['ErrorMessage'] = $result['errorMessage'] . ' '
                                            . implode(',', array_keys($result['failedSkills']));
            } else {
                $response['Verifications'] = $result['verifications'];
            }
        }

        $this->view->assignMultiple($response);
        return $this->createResponse();
    }

    public static function convertGroupsToJson(array $groups): array
    {
        return array_map(function (array $group) {
            /** @var Certification $verification */
            $verification = $group['certs'][0];
            $jsonData = $verification->toJsonData();
            $jsonData['skillCount'] = count($group['certs']);
            /** @var Certification $cert */
            foreach ($group['certs'] as $cert) {
                $skill = $cert->getSkill();
                if ($skill) {
                    $jsonData['skills'][] = $skill->getUid();
                }
            }
            return $jsonData;
        }, $groups);
    }
}
