<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Controller;

use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Controller\OrganisationController;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\OrganisationStatistics;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Exception;

class OrganisationControllerTest extends AbstractFunctionalControllerTestCaseBase
{
    /** @var OrganisationController|MockObject|AccessibleObjectInterface */
    protected $subject = null;

    /** @var BrandRepository */
    protected $brandRepository;

    /**
     * @throws DBALException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->brandRepository = $this->objectManager->get(BrandRepository::class);

        $this->subject = $this->getAccessibleMock(OrganisationController::class,
            ['redirect', 'forward', 'addFlashMessage', 'getCurrentUser']);
        $this->subject->injectObjectManager($this->objectManager);
        $this->subject->_set('view', $this->view);
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

        $this->assertCount(1, $stats->getInterestSets());
    }
}
