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

use DateTime;
use Psr\Http\Message\ResponseInterface;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Model\VerificationCreditPack;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\RequirementRepository;
use SkillDisplay\Skills\Domain\Repository\RewardRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditPackRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditUsageRepository;
use SkillDisplay\Skills\Service\CsvService;
use SkillDisplay\Skills\Service\VerificationService;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class BackendVerificationManagerController extends BackendController
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
        protected readonly VerificationCreditPackRepository $verificationCreditPackRepository,
        protected readonly VerificationCreditUsageRepository $verificationCreditUsageRepository,
        protected readonly UserRepository $userRepository,
    ) {
        $this->menuItems = [
            'creditOverview' => [
                'controller' => 'BackendVerificationManager',
                'action' => 'creditOverview',
                'label' => 'backend.creditOverview',
            ],
            'generateVerificationCSV' => [
                'controller' => 'BackendVerificationManager',
                'action' => 'verificationManager',
                'label' => 'backend.verificationManager',
            ],
        ];
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

    public function verificationManagerAction(): ResponseInterface
    {
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Skills/ReportingBackend');

        $this->view->assign('organizations', $this->brandRepository->findAll());
        $this->view->assign('fromDate', date('Y-m-d'));
        return $this->generateOutput();
    }

    public function creditOverviewAction(): ResponseInterface
    {
        $organizations = $this->brandRepository->findAll();
        $data = [];
        foreach ($organizations as $organization) {
            $balance = $this->verificationService->getBalanceForOrganisation($organization);
            $data[] = [
                'organization' => $organization,
                'points' => $balance['points'],
                'balance' => $balance['balance'],
            ];
        }
        $this->view->assign('data', $data);
        return $this->generateOutput();
    }

    /**
     * @throws InvalidQueryException
     */
    public function creditHistoryAction(Brand $organization): ResponseInterface
    {
        // get package history of organization
        /** @var VerificationCreditPack[] $packs */
        $packs = $this->verificationCreditPackRepository->findByBrand($organization)->toArray();
        $totalPackagePrice = 0;
        foreach ($packs as $pack) {
            $totalPackagePrice += $pack->getPrice();
        }
        // get verifications with credits of organization
        $usages = $this->getVerificationHistoryForOrganization($organization);

        $this->view->assign('packs', $packs);
        $this->view->assign('totalPackagePrice', $totalPackagePrice);
        $this->view->assign('usages', $usages);
        $this->view->assign('organization', $organization);
        return $this->generateOutput();
    }

    /**
     * @throws InvalidQueryException
     */
    private function getVerificationHistoryForOrganization(Brand $organization): array
    {
        $groups = $this->certificationRepository->findAcceptedByOrganisation($organization, new DateTime('@0'), new DateTime());
        $usages = [];
        foreach ($groups as $group) {
            $points = 0;
            $price = 0;
            /** @var Certification $cert */
            foreach ($group['certs'] as $cert) {
                $points += $cert->getPoints();
                $price += $cert->getPrice();
            }
            if (isset($cert)) {
                $jsonData = $cert->toJsonData(true);
                $jsonData['credits'] = $points;
                $jsonData['price'] = $price;
                $usages[] = $jsonData;
            }
        }
        // sort usages newest first
        usort($usages, fn($a, $b) => $b['grantDate']->getTimestamp() - $a['grantDate']->getTimestamp());
        return $usages;
    }

    public function generateCSVAction(string $dateFrom, string $dateTo, array $organizations): never
    {
        $fromDate = $this->convertDate($dateFrom . ' 00:00:00');
        if ($fromDate == null) {
            $this->addFlashMessage('Please select a start date!', 'Error', ContextualFeedbackSeverity::ERROR);
        }
        $toDate = $this->convertDate($dateTo . ' 23:59:59');
        $lines = [];
        foreach ($organizations as $organizationId) {
            /** @var ?Brand $organization */
            $organization = $this->brandRepository->findByUid($organizationId);
            /** @var ?User $brandManager */
            $brandManager = $this->userRepository->findManagers($organization)[0] ?? null;
            $groups = $this->certificationRepository->findAcceptedByOrganisation($organization, $fromDate, $toDate ?: new DateTime());
            $creditsSpent = 0;
            $numVerificationsWithCredits = 0;
            $numVerificationsWithoutCredits = 0;
            $price = 0;
            /** @var Certification $group */
            foreach ($groups as $group) {
                /** @var Certification $verification */
                foreach ($group['certs'] as $verification) {
                    $creditUsages = $this->verificationCreditUsageRepository->findByVerification($verification)->toArray();
                    if (count($creditUsages) > 0) {
                        $numVerificationsWithCredits++;
                        $creditsSpent += $verification->getPoints();
                    } else {
                        $numVerificationsWithoutCredits++;
                        $price += $verification->getPrice();
                    }
                }
            }
            $lines[] = [
                $organization->getName(),
                $organization->getBillingAddress(),
                $organization->getCountry(),
                $organization->getVatId(),
                $brandManager ? $brandManager->getFirstName() . ' ' . $brandManager->getLastName() : '',
                $this->verificationCreditPackRepository->getAvailableCredit((int)$organizationId, $fromDate),
                $this->verificationCreditPackRepository->findReceivedCredits((int)$organizationId, $fromDate, $toDate),
                $creditsSpent,
                $numVerificationsWithCredits,
                $numVerificationsWithoutCredits,
                $price,
            ];
        }
        //set the column names
        array_unshift($lines, [
            'Organisation',
            'Billing Address',
            'Country',
            'VAT ID',
            'Organisation Manager',
            'Credits Am Anfang des Zeitraums',
            'Erhaltene Credits',
            'Verbrauchte Credits',
            'Anzahl Verifizierungen mit Credits',
            'Anzahl Verifizierungen ohne Credits',
            'Offener Betrag',
        ]);
        $filename = 'Organisation_Verifications_' . $fromDate->format('Y-m-d') . '-' . ($toDate ? $toDate->format('Y-m-d') : date('Y-m-d')) . '.csv';

        CsvService::sendCSVFile($lines, $filename);
    }

    private function convertDate(string $date): ?DateTime
    {
        $format = LocalizationUtility::translate('backend.date.date_format-presentation', 'Skills') . ' G:i:s';
        $date = DateTime::createFromFormat($format, $date);
        return $date === false ? null : $date;
    }
}
