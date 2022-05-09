<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Hook;

use Doctrine\DBAL\DBALException;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditUsageRepository;
use SkillDisplay\Skills\Hook\DataHandlerHook;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Exception;

/**
 * Test case.
 *
 * @author Mattthias Böhm <matthias.boehm@reelworx.at>
 */
class DataHandlerHookTest extends AbstractFunctionalTestCaseBase
{
    /** @var DataHandlerHook */
    protected $dataHandlerHook;

    /** @var VerificationCreditUsageRepository */
    private $verificationcreditUsageRepository;

    /**
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->dataHandlerHook = GeneralUtility::makeInstance(DataHandlerHook::class);
        $this->verificationcreditUsageRepository = $this->objectManager->get(VerificationCreditUsageRepository::class);

        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_verificationcreditpack.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_certification.xml');
    }

    /**
     * @test
     */
    public function packagesHandleDebtsCorrectlyPackageWithPrice()
    {
        $this->dataHandlerHook->processDatamap_afterDatabaseOperations(
            '',
            'tx_skills_domain_model_verificationcreditpack',
            '1',
            [],
            new DataHandler());

        $usages = $this->verificationcreditUsageRepository->findAll()->toArray();

        $this->assertCount(11, $usages);
    }

    /**
     * @test
     */
    public function packagesHandleDebtsCorrectlyPackageWithoutPrice()
    {
        $this->dataHandlerHook->processDatamap_afterDatabaseOperations(
            '',
            'tx_skills_domain_model_verificationcreditpack',
            '2',
            [],
            new DataHandler());

        $usages = $this->verificationcreditUsageRepository->findAll()->toArray();

        $this->assertCount(4, $usages);
    }

    /**
     * @test
     */
    public function packagesNoDebtHandlingForPackageWithPoints()
    {
        $this->dataHandlerHook->processDatamap_afterDatabaseOperations(
            '',
            'tx_skills_domain_model_verificationcreditpack',
            '3',
            [],
            new DataHandler());

        $usages = $this->verificationcreditUsageRepository->findAll()->toArray();

        $this->assertCount(0, $usages);
    }
}
