<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Repository;

use Doctrine\DBAL\DBALException;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\UserOrganisationsService;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\TestingFramework\Core\Exception;

class SkillRepositoryTest extends AbstractFunctionalTestCaseBase
{
    /** @var SkillRepository */
    protected $skillRepository;

    /** @var UserRepository */
    protected $userRepository;

    /**
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->skillRepository = $this->objectManager->get(SkillRepository::class);
        $this->userRepository = $this->objectManager->get(UserRepository::class);

        $this->importDataSet(__DIR__ . '/../Fixtures/user_access_test.xml');
    }

    /**
     * @test
     */
    public function searchOnlyShowsPublicSkillsForGuests()
    {
        $result = $this->skillRepository->findBySearchWord('TestSkill', []);
        self::assertCount(1,$result);
    }

    /**
     * @test
     */
    public function searchShowsOrganisationSkillsForMembers()
    {
        $user = $this->userRepository->findByUsername('muster');
        $result = $this->skillRepository->findBySearchWord('TestSkill', UserOrganisationsService::getOrganisationsOrEmpty($user));
        self::assertCount(2,$result);
    }
}
