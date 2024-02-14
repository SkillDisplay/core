<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Controller;

use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Controller\OrganisationController;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\OrganisationStatistics;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\InvitationCodeRepository;
use SkillDisplay\Skills\Domain\Repository\OrganisationStatisticsRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Tests\Functional\SimulateLoginTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Exception;

class OrganisationControllerTest extends AbstractFunctionalControllerTestCaseBase
{
    use SimulateLoginTrait;

    protected OrganisationController|MockObject|AccessibleObjectInterface $subject;

    protected BrandRepository $brandRepository;

    /**
     * @throws DBALException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->brandRepository = GeneralUtility::makeInstance(BrandRepository::class);

        $this->subject = $this->getAccessibleMock(
            OrganisationController::class,
            ['addFlashMessage', 'getCurrentUser'],
            [
                $this->userRepository,
                GeneralUtility::makeInstance(BrandRepository::class),
                GeneralUtility::makeInstance(SkillPathRepository::class),
                GeneralUtility::makeInstance(OrganisationStatisticsRepository::class),
                GeneralUtility::makeInstance(CertificationRepository::class),
                GeneralUtility::makeInstance(CertifierRepository::class),
                GeneralUtility::makeInstance(InvitationCodeRepository::class),
            ]
        );
        $this->initController($this->subject);
    }

    /**
     * @throws Exception
     */
    protected function setUpDatabase(): void
    {
        $this->importDataSet(__DIR__ . '/../Fixtures/fe_users.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/organisation_statistics.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_user_brand_mm.xml');
    }

    /**
     * @test
     */
    public function organisationStatisticsHidesNonOrganisationSets()
    {
        $this->simulateLogin();
        /** @var Brand $brand */
        $brand = $this->brandRepository->findByUid(1);
        $this->subject->organisationStatisticsAction($brand);

        /** @var OrganisationStatistics $stats */
        $stats = $this->view->_get('variables')['organisationStatistics'];

        self::assertCount(1, $stats->getInterestSets());
    }
}
