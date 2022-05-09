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

use DateTime;
use InvalidArgumentException;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Domain\Model\Brand;
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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

class CertificationController extends AbstractController
{
    protected CertificationRepository $certificationRepository;

    public function __construct(CertificationRepository $repo)
    {
        $this->certificationRepository = $repo;
    }

    public function showAction(Certification $verification)
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('');
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

        $group = $verification->getRequestGroup();
        $verificationJson = $verification->toJsonData($userIsVerifier, !$userIsVerifier && $user !== $verificationOwner);
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
            $verificationService = GeneralUtility::makeInstance(VerificationService::class);
            $verificationService->setCreditSettings($this->settings['credits']);
            $neededPoints = $verificationService->calculatePointsNeeded($certs);
            $organisation = $verification->getBrand();
            $acceptable = $verification->isPending() && ($organisation->getCreditOverdraw() || $verificationService->organisationHasEnoughCredit($organisation->getUid(), $neededPoints));
            $verificationJson['canBeAccepted'] = $acceptable;
            $verificationJson['credits'] = $neededPoints;
            $verificationJson['price'] = (float)$this->settings['credits']['price'];
            $verificationJson['verifier']['isOrgaManager'] = in_array($organisation, $user->getManagedBrands()->toArray());
        }
        $verification = $verificationJson;

        $this->view->assign('verification', $verification);
    }

    /**
     * note: no type annotation for $verifications since it can be array|Objectstorage but extbase can't deal with it
     *
     * @param array $verifications
     * @param bool $accept
     * @param bool $decline
     * @param bool $revoke
     * @param string $reason
     * @return string|null
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws UnknownObjectException
     */
    public function modifyAction(
        $verifications,
        bool $accept = false,
        bool $decline = false,
        bool $revoke = false,
        string $reason = ''
    ) {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('');
        }

        if (!($accept xor $decline xor $revoke)) {
            throw new InvalidArgumentException("Wrong arguments. You need to define exactly 1 action.");
        }

        /** @var VerificationService $verificationService */
        $verificationService = $this->objectManager->get(VerificationService::class);
        $verificationService->setCreditSettings($this->settings['credits']);

        $success = true;
        $errorMessage = '';

        /** @var Certification[] $certObjects */
        $certObjects = [];
        $verification = null;
        foreach ($verifications as $verificationId) {
            /** @var Certification $verification */
            $verification = $this->certificationRepository->findByUid($verificationId);
            if (!$verification) {
                $errorMessage = 'Invalid verification ID ' . $verificationId;
                $success = false;
                break;
            }
            if ($verification->getLevelNumber() === Skill::LevelTierMap['self'] || $verification->getCertifier()->getUser()->getUid() === $user->getUid()) {
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
            $totalCreditPointsNeeded = $verificationService->calculatePointsNeeded($certObjects);
            $organisation = $verification->getCertifier()->getBrand();
            if (!$verificationService->organisationHasEnoughCredit($organisation->getUid(), $totalCreditPointsNeeded) && !$organisation->getCreditOverdraw()) {
                $errorMessage = 'Verification credit points are insufficient. Needed points: ' . $totalCreditPointsNeeded;
                $success = false;
            }
        }
        if ($success) {
            foreach ($certObjects as $verification) {
                $verificationService->confirmSkillUp($verification, $accept, $decline, $revoke, $reason);
            }
        }

        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['success', 'error']);
            $this->view->assign('error', $errorMessage);
            $this->view->assign('success', $success);
            return null;
        }
        return '';
    }

    public function userCancelAction(array $verifications): ?string
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('');
        }
        $success = true;
        /** @var VerificationService $verificationService */
        $verificationService = $this->objectManager->get(VerificationService::class);
        /** @var Certification $certification */
        foreach ($verifications as $certificationId) {
            $certification = $this->certificationRepository->findByUid($certificationId);
            if ($certification) {
                if ($certification->getUser()->getUid() === $user->getUid()) {
                    $verificationService->cancelSkillupRequest($certification);
                } else {
                    $success = false;
                    break;
                }
            } else {
                throw new InvalidArgumentException('Given certification id is invalid:' . $certificationId);
            }
        }

        if ($this->view instanceof JsonView) {
            $this->view->setVariablesToRender(['success', 'dummy']);
            $this->view->assign('success', $success);
            return null;
        }
        return '';
    }

    public function recentAction()
    {
        $user = $this->getCurrentUser(false);
        if ($user) {
            $verifications = array_merge($user->getAcceptedCertifications(), $user->getPendingCertifications());
            //sort newest first
            usort($verifications, function(array $a, array $b) {
                /** @var Certification $certA */
                $certA = $a['certs'][0];
                /** @var Certification $certB */
                $certB = $b['certs'][0];
                return $certB->getCrdate() - $certA->getCrdate();
            });
            array_splice($verifications, 5); // only keep 15 items
            $verifications = $this->convertGroupsToJson($verifications);
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
    }

    public function recentRequestsAction()
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('');
        }
        $groups = $this->certificationRepository->findPendingByCertifierUser($user, 5);

        $verifications = $this->convertGroupsToJson($groups);

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
    }

    public function listForVerifierAction(Certifier $verifier)
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('');
        }
        $verifications = [];

        if ($verifier->getUser() && $verifier->getUser()->getUid() === $user->getUid()) {
            $groups = $this->certificationRepository->findByCertifier($verifier);
            $verifications = $this->convertGroupsToJson($groups);
            /** @var VerificationService $verificationService */
            $verificationService = GeneralUtility::makeInstance(VerificationService::class);
            $verificationService->setCreditSettings($this->settings['credits']);
            /** @var BrandRepository $brandRepository */
            $brandRepository = GeneralUtility::makeInstance(BrandRepository::class);
            foreach ($verifications as &$request) {
                if ($request['requestGroup']) {
                    $certs = $this->certificationRepository->findByRequestGroup($request['requestGroup'])->toArray();
                } else {
                    $certs = [$this->certificationRepository->findByUid($request['uid'])];
                }
                $neededPoints = $verificationService->calculatePointsNeeded($certs);
                $organisation = $brandRepository->findByUid((int)$request['brandId']);
                $request['canBeAccepted'] = $certs[0]->isPending() && ($organisation->getCreditOverdraw() ||
                        $verificationService->organisationHasEnoughCredit($organisation->getUid(), $neededPoints));
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
    }

    public function listForOrganisationAction(Brand $organisation)
    {
        $user = $this->getCurrentUser(false);
        if (!$user || !$user->getManagedBrands()->contains($organisation)) {
            throw new AuthenticationException('');
        }
        /** @var VerificationCreditUsageRepository $verificationCreditUsageRepository */
        $verificationCreditUsageRepository = GeneralUtility::makeInstance(VerificationCreditUsageRepository::class);
        /** @var VerificationCreditPackRepository $verificationCreditPackRepository */
        $verificationCreditPackRepository = GeneralUtility::makeInstance(VerificationCreditPackRepository::class);
        $groups = $this->certificationRepository->findAcceptedByOrganisation($organisation, new DateTime('@0'), new DateTime());
        $usages = [];
        if ($organisation) {
            /** @var Certification $verification */
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
                if ($cert) {
                    $billingDate = $cert->getGrantDate();
                    $creditUsages = $verificationCreditUsageRepository->findByVerification($cert)->toArray();
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
        }
        $this->view->assign('usages', $usages);
    }

    public function historyAction()
    {
        $user = $this->getCurrentUser(false);
        if (!$user) {
            throw new AuthenticationException('');
        }
        $groups = $this->certificationRepository->findByUser($user);
        $verifications = $this->convertGroupsToJson($groups);

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
    }

    /**
     * @param string $apiKey
     */
    public function createAction(string $apiKey)
    {
        $response = [
            "Version" => "1.0",
            "ErrorMessage" => "",
            "Verifications" => [],
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

        if ($apiKey === '') {
            $response['ErrorMessage'] = 'Missing API key';
            $this->view->assignMultiple($response);
            return;
        }

        $postData = file_get_contents('php://input');
        if ($postData === '') {
            $response['ErrorMessage'] = 'No data sent';
            $this->view->assignMultiple($response);
            return;
        }

        $userOfRequest = $this->getCurrentUser(false, $apiKey);
        if (!$userOfRequest) {
            $response['ErrorMessage'] = 'Invalid API key';
            $this->view->assignMultiple($response);
            return;
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

        if ($signatureFromClient === '') {
            $response['ErrorMessage'] = 'Missing signature';
            $this->view->assignMultiple($response);
            return;
        }
        if ($skillId === 0 && $skillSetId === 0) {
            $response['ErrorMessage'] = 'No skill or skillset given';
            $this->view->assignMultiple($response);
            return;
        }
        $tier = Skill::LevelTierMap[$level] ?? 0;
        if (!$tier) {
            $response['ErrorMessage'] = 'Invalid level';
            $this->view->assignMultiple($response);
            return;
        }
        if ($verifierId === 0 && $level !== 'self') {
            $response['ErrorMessage'] = 'Missing verifierId';
            $this->view->assignMultiple($response);
            return;
        }

        /** @var Certifier $verifier */
        $verifier = null;
        $secret = 'sdself';
        if ($level !== 'self') {
            $verifier = $this->objectManager->get(CertifierRepository::class)->findByUid($verifierId);
            if (!$verifier) {
                $response['ErrorMessage'] = 'Invalid verifier';
                $this->view->assignMultiple($response);
                return;
            }
            $secret = $verifier->getSharedApiSecret();
            if ($secret === '') {
                $response['ErrorMessage'] = 'Verifier has no shared secret';
                $this->view->assignMultiple($response);
                return;
            }
        }

        $data['Signature'] = '';
        $signature = hash('sha256', json_encode($data) . $secret);
        $signatureOk = hash_equals($signature, $signatureFromClient);
        if (!$signatureOk) {
            $response['ErrorMessage'] = 'Invalid signature';
            $this->view->assignMultiple($response);
            return;
        }

        if ($username === '') {
            $response['ErrorMessage'] = 'No username';
            $this->view->assignMultiple($response);
            return;
        }
        $userRepo = $this->objectManager->get(UserRepository::class);
        $user = $userRepo->findByUsername($username);
        if (!$user) {
            $response['ErrorMessage'] = 'Invalid username';
            $this->view->assignMultiple($response);
            return;
        }

        $campaign = null;
        if ($campaignId) {
            $campaignRepo = $this->objectManager->get(CampaignRepository::class);
            $campaign = $campaignRepo->findByUid($campaignId);
            if (!$campaign) {
                $response['ErrorMessage'] = 'Invalid campaign';
                $this->view->assignMultiple($response);
            }
        }

        /** @var VerificationService $verificationService */
        $verificationService = $this->objectManager->get(VerificationService::class);
        $verificationService->setCreditSettings($this->settings['credits']);
        if ($skillId) {
            /** @var Skill $skill */
            $skill = $this->objectManager->get(SkillRepository::class)->findByUid($skillId);
            if (!$skill) {
                $response['ErrorMessage'] = 'Invalid skillId';
                $this->view->assignMultiple($response);
                return;
            }
            if ($tier !== 3) {
                $allowedVerifiers = $verificationService->getVerifiersForSkills([$skill], $user, $tier);
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
                    return;
                }
            }
            $result = $verificationService->handleSkillUpRequest([$skill], '', $user, $tier, '', $verifier, $campaign, $autoConfirm);
            if ($result['errorMessage']) {
                $response['ErrorMessage'] = $result['errorMessage'] . ' ' .
                                            $result['failedSkills'][$skill->getUid()]['reason'];
            } else {
                $response['Verifications'] = $result['verifications'];
            }
        } elseif ($skillSetId) {
            /** @var SkillPath $skillSet */
            $skillSet = $this->objectManager->get(SkillPathRepository::class)->findByUid($skillSetId);
            if (!$skillSet) {
                $response['ErrorMessage'] = 'Invalid skillSetId';
                $this->view->assignMultiple($response);
                return;
            }
            $skills = $skillSet->getSkills()->toArray();
            if ($tier !== 3) {
                $allowedVerifiers = $verificationService->getVerifiersForSkills($skills, $user, $tier);
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
                    return;
                }
            }
            $result = $verificationService->handleSkillUpRequest($skills, $skillSet->getSkillGroupId(), $user, $tier,
                '', $verifier, $campaign, $autoConfirm);
            if ($result['errorMessage']) {
                $response['ErrorMessage'] = $result['errorMessage'] . ' '
                                            . implode(',', array_keys($result['failedSkills']));
            } else {
                $response['Verifications'] = $result['verifications'];
            }
        }

        $this->view->assignMultiple($response);
    }

    public static function convertGroupsToJson(array $groups): array
    {
        return array_map(function (array $group) {
            /** @var Certification $verification */
            $verification = $group['certs'][0];
            $jsonData = $verification->toJsonData();
            $jsonData['skillCount'] = count($group['certs']);
            return $jsonData;
        }, $groups);
    }
}
