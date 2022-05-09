<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Service;

use Doctrine\DBAL\DBALException;
use SkillDisplay\Skills\Service\ShortLinkService;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\TestingFramework\Core\Exception;

class ShortLinkServiceTest extends AbstractFunctionalTestCaseBase
{
    /**
     * @throws DBALException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_shortlink.xml');
    }

    /**
     * @test
     */
    public function handleShortlinkReturnsExpectedAction()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionCode(1474505954);
        $shortLinkService = $this->objectManager->get(ShortLinkService::class);
        $shortLinkService->handleShortlink('AEF');
    }
}
