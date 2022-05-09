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

use DateTime;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Model\VerificationCreditPack;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditPackRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditUsageRepository;
use SkillDisplay\Skills\Service\CsvService;
use SkillDisplay\Skills\Service\VerificationService;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class BackendVerificationManagerController extends BackendController
{
    protected function initializeView(ViewInterface $view): void
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        if ($view instanceof BackendTemplateView) {
            $this->generateMenu();
            //$this->generateButtons();
        }
    }

    protected function generateMenu(): void
    {
        $menuItems = [];
        $menuItems['creditOverview'] = [
            'controller' => 'BackendVerificationManager',
            'action' => 'creditOverview',
            'label' => 'backend.creditOverview',
        ];
        $menuItems['generateVerificationCSV'] = [
            'controller' => 'BackendVerificationManager',
            'action' => 'verificationManager',
            'label' => 'backend.verificationManager',
        ];

        $menu = $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('SkillsModuleMenu');

        foreach ($menuItems as $menuItemConfig) {
            $isActive = $this->request->getControllerName() === $menuItemConfig['controller']
                && $this->request->getControllerActionName() === $menuItemConfig['action'];
            $menuItem = $menu->makeMenuItem()
                ->setTitle($this->translate($menuItemConfig['label']))
                ->setHref($this->getHref($menuItemConfig['controller'], $menuItemConfig['action']))
                ->setActive($isActive);
            $menu->addMenuItem($menuItem);
        }

        $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
    }

    private static function translate(string $label): ?string
    {
        return LocalizationUtility::translate($label, 'skills');
    }

    public function verificationManagerAction()
    {
        GeneralUtility::makeInstance(PageRenderer::class)->loadRequireJsModule("TYPO3/CMS/Skills/ReportingBackend");

        $this->view->assign('organizations', $this->brandRepository->findAllSortedAlphabetically());
        $this->view->assign('fromDate', date('Y-m-d'));
    }

    public function creditOverviewAction()
    {
        $organizations = $this->brandRepository->findAllSortedAlphabetically();
        $data = [];
        $verificationService = GeneralUtility::makeInstance(VerificationService::class);
        foreach ($organizations as $organization) {
            $balance = $verificationService->getBalanceForOrganisation($organization);
            $data[] = [
                'organization' => $organization,
                'points' => $balance['points'],
                'balance' => $balance['balance']
            ];
        }
        $this->view->assign('data', $data);
    }

    public function creditHistoryAction(Brand $organization)
    {
        $verificationCreditPackRepository = GeneralUtility::makeInstance(VerificationCreditPackRepository::class);
        // get package history of organization
        $packs = $verificationCreditPackRepository->findByBrand($organization->getUid())->toArray();
        $totalPackagePrice = 0;
        /** @var VerificationCreditPack $pack */
        foreach ($packs as $pack) {
            $totalPackagePrice += $pack->getPrice();
        }
        // get verifications with credits of organization
        $usages = $this->getVerificationHistoryForOrganization($organization);

        $this->view->assign('packs', $packs);
        $this->view->assign('totalPackagePrice', $totalPackagePrice);
        $this->view->assign('usages', $usages);
        $this->view->assign('organization', $organization);
    }

    private function getVerificationHistoryForOrganization(Brand $organization)
    {
        $verificationRepository = GeneralUtility::makeInstance(CertificationRepository::class);
        $groups = $verificationRepository->findAcceptedByOrganisation($organization, new DateTime('@0'), new DateTime());
        $usages = [];
        /** @var Certification $verification */
        foreach ($groups as $group) {
            $points = 0;
            $price = 0;
            /** @var Certification $cert */
            foreach ($group['certs'] as $cert) {
                $points += $cert->getPoints();
                $price += $cert->getPrice();
            }
            $jsonData = $cert->toJsonData(true);
            $jsonData['credits'] = $points;
            $jsonData['price'] = $price;
            $usages[] = $jsonData;
        }
        // sort usages newest first
        usort($usages, fn($a, $b) => $b['grantDate']->getTimestamp() - $a['grantDate']->getTimestamp());
        return $usages;
    }

    /***
     * @param string $dateFrom
     * @param string $dateTo
     * @param array $organizations
     */
    public function generateCSVAction(string $dateFrom, string $dateTo, array $organizations)
    {
        $fromDate = $this->convertDate($dateFrom . ' 00:00:00');
        if ($fromDate == null) {
            $this->addFlashMessage('Please select a start date!', 'Error', AbstractMessage::ERROR);
        }
        $toDate = $this->convertDate($dateTo . ' 23:59:59');
        /** @var UserRepository $userRepository */
        $userRepository = GeneralUtility::makeInstance(UserRepository::class);
        /** @var VerificationCreditPackRepository $verificationCreditPackRepository */
        $verificationCreditPackRepository = GeneralUtility::makeInstance(VerificationCreditPackRepository::class);
        /** @var VerificationCreditUsageRepository $verificationCreditUsageRepository */
        $verificationCreditUsageRepository = GeneralUtility::makeInstance(VerificationCreditUsageRepository::class);
        /** @var CertificationRepository $verificationRepository */
        $verificationRepository = GeneralUtility::makeInstance(CertificationRepository::class);
        $lines = [];
        foreach ($organizations as $organizationId) {
            /** @var Brand $organization */
            $organization = $this->brandRepository->findByUid($organizationId);
            /** @var User $brandManager */
            $brandManager = $userRepository->findManagers($organization)[0] ?? null;
            $groups = $verificationRepository->findAcceptedByOrganisation($organization, $fromDate, $toDate ?: new DateTime());
            $creditsSpent = 0;
            $numVerificationsWithCredits = 0;
            $numVerificationsWithoutCredits = 0;
            $price = 0;
            /** @var Certification $group */
            foreach ($groups as $group) {
                /** @var Certification $verification */
                foreach ($group['certs'] as $verification) {
                    $creditUsages = $verificationCreditUsageRepository->findByVerification($verification)->toArray();
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
                $verificationCreditPackRepository->getAvailableCredit((int)$organizationId, $fromDate),
                $verificationCreditPackRepository->findReceivedCredits((int)$organizationId, $fromDate, $toDate),
                $creditsSpent,
                $numVerificationsWithCredits,
                $numVerificationsWithoutCredits,
                $price
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
            'Offener Betrag'
        ]);
        $filename = 'Organisation_Verifications_' . $fromDate->format('Y-m-d') . '-' . ($toDate ? $toDate->format('Y-m-d') : date('Y-m-d')) . '.csv';

        CsvService::sendCSVFile($lines, $filename);
    }

    private function convertDate(string $date): ?\DateTime
    {
        $format = LocalizationUtility::translate('backend.date.date_format-presentation', 'Skills') . ' G:i:s';
        $date = \DateTime::createFromFormat($format, $date);
        return $date === false ? null : $date;
    }
}
