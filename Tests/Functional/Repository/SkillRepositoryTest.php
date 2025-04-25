<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Repository;

use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\UserOrganisationsService;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;

class SkillRepositoryTest extends AbstractFunctionalTestCaseBase
{
    protected SkillRepository $skillRepository;
    /** @var UserRepository */
    protected UserRepository $userRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->skillRepository = GeneralUtility::makeInstance(SkillRepository::class);
        $this->userRepository = GeneralUtility::makeInstance(UserRepository::class);

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/user_access_test.csv');
    }

    /**
     * @test
     * @throws InvalidQueryException
     */
    public function searchOnlyShowsPublicSkillsForGuests(): void
    {
        $result = $this->skillRepository->findBySearchWord('TestSkill', []);
        self::assertCount(1, $result);
    }

    /**
     * @test
     * @throws InvalidQueryException
     */
    public function searchShowsOrganisationSkillsForMembers(): void
    {
        $user = $this->userRepository->findByUsername('muster');
        $result = $this->skillRepository->findBySearchWord('TestSkill', UserOrganisationsService::getOrganisationsOrEmpty($user));
        self::assertCount(2, $result);
    }
}
