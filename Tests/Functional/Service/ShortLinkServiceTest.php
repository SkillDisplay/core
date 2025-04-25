<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Service;

use InvalidArgumentException;
use SkillDisplay\Skills\Service\ShortLinkService;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ShortLinkServiceTest extends AbstractFunctionalTestCaseBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_shortlink.csv');
    }

    /**
     * @test
     */
    public function handleShortlinkReturnsExpectedAction(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionCode(1474505954);
        $shortLinkService = GeneralUtility::makeInstance(ShortLinkService::class);
        $shortLinkService->handleShortlink('AEF');
    }
}
