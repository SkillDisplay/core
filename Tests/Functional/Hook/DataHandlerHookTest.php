<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Hook;

use SkillDisplay\Skills\Domain\Repository\VerificationCreditUsageRepository;
use SkillDisplay\Skills\Hook\DataHandlerHook;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Exception;

class DataHandlerHookTest extends AbstractFunctionalTestCaseBase
{
    protected DataHandlerHook $dataHandlerHook;
    protected VerificationCreditUsageRepository $verificationcreditUsageRepository;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->dataHandlerHook = GeneralUtility::makeInstance(DataHandlerHook::class);
        $this->verificationcreditUsageRepository = GeneralUtility::makeInstance(VerificationCreditUsageRepository::class);

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_verificationcreditpack.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_certification.csv');
    }

    /**
     * @test
     */
    public function packagesHandleDebtsCorrectlyPackageWithPrice(): void
    {
        $this->dataHandlerHook->processDatamap_afterDatabaseOperations(
            '',
            'tx_skills_domain_model_verificationcreditpack',
            '1',
            [],
            new DataHandler()
        );

        $usages = $this->verificationcreditUsageRepository->findAll()->toArray();

        self::assertCount(11, $usages);
    }

    /**
     * @test
     */
    public function packagesHandleDebtsCorrectlyPackageWithoutPrice(): void
    {
        $this->dataHandlerHook->processDatamap_afterDatabaseOperations(
            '',
            'tx_skills_domain_model_verificationcreditpack',
            '2',
            [],
            new DataHandler()
        );

        $usages = $this->verificationcreditUsageRepository->findAll()->toArray();

        self::assertCount(4, $usages);
    }

    /**
     * @test
     */
    public function packagesNoDebtHandlingForPackageWithPoints(): void
    {
        $this->dataHandlerHook->processDatamap_afterDatabaseOperations(
            '',
            'tx_skills_domain_model_verificationcreditpack',
            '3',
            [],
            new DataHandler()
        );

        $usages = $this->verificationcreditUsageRepository->findAll()->toArray();

        self::assertCount(0, $usages);
    }
}
