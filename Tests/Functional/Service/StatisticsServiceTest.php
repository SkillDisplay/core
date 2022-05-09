<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Service;

use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Domain\Model\Award;
use SkillDisplay\Skills\Domain\Repository\AwardRepository;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\OrganisationStatisticsRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\StatisticsService;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Exception;

class StatisticsServiceTest extends AbstractFunctionalTestCaseBase
{
    /** @var StatisticsService|MockObject|AccessibleObjectInterface */
    protected $statisticsService;

    /** @var OrganisationStatisticsRepository */
    protected $organisationStatisticsRepository;

    /** @var BrandRepository */
    protected $brandRepository;

    /**
     * @throws DBALException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->organisationStatisticsRepository = $this->objectManager->get(OrganisationStatisticsRepository::class);
        $this->brandRepository = $this->objectManager->get(BrandRepository::class);

        $this->importDataSet(__DIR__ . '/../Fixtures/fe_users.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_brand.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_certifier.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_skill.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_certifierpermission.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_skill_brand_mm.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_user_brand_mm.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_user_organisation_mm.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_certification.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_skillpath.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_skillpath_skill_mm.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_skillset_brand_mm.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_membershiphistory.xml');

        $this->statisticsService = $this->getAccessibleMock(StatisticsService::class, ['dummy'], [
            $this->objectManager->get(BrandRepository::class),
            $this->objectManager->get(UserRepository::class),
            $this->objectManager->get(CertifierRepository::class),
            $this->objectManager->get(CertificationRepository::class),
            $this->objectManager->get(SkillPathRepository::class),
            $this->objectManager->get(SkillRepository::class),
            $this->objectManager->get(PersistenceManager::class),
        ]);
    }

    /**
     * @test
     */
    public function runReturnsExpectedAwardCounts()
    {
        $this->statisticsService->run();

        /** @var AwardRepository $awardRepo */
        $awardRepo = $this->objectManager->get(AwardRepository::class);

        $verifiedAwards = $awardRepo->getAwardsByType(Award::TYPE_VERIFICATIONS);
        self::assertCount(21, $verifiedAwards);

        $memberAwards = $awardRepo->getAwardsByType(Award::TYPE_MEMBER);
        self::assertCount(8, $memberAwards);

        $coachAwards = $awardRepo->getAwardsByType(Award::TYPE_COACH);
        self::assertCount(3, $coachAwards);

        $mentorAwards = $awardRepo->getAwardsByType(Award::TYPE_MENTOR);
        self::assertCount(1, $mentorAwards);
    }

    public function runReturnsCorrectNumberOfAwardsForUserDataProvider(): array
    {
        return [
            'user 1' => [1, 8],
            'user 8' => [8, 3]
        ];
    }

    /**
     * @test
     * @dataProvider runReturnsCorrectNumberOfAwardsForUserDataProvider
     * @param int $userId
     * @param int $expected
     * @throws DBALException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function runReturnsCorrectNumberOfAwardsForUser(int $userId, int $expected)
    {
        $this->statisticsService->run();

        /** @var AwardRepository $awardRepo */
        $awardRepo = $this->objectManager->get(AwardRepository::class);
        $userAwards = $awardRepo->getAwardsByUserId($userId);
        self::assertCount($expected, $userAwards);
    }

    /**
     * @test
     * @throws DBALException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    public function calculateOrganisationStatisticsReturnsNumberOfStatistics()
    {
        // set time to 1. 8. 2019
        $GLOBALS['SIM_EXEC_TIME'] = 1564617600;
        $this->statisticsService->calculateOrganisationStatistics();

        $statistics = $this->organisationStatisticsRepository->getOrganisationStatisticsForBrand(1);

        self::assertEquals([
            [
                'points' => 7,
                'label' => 'Erasmus+'
            ],
            [
                'points' => 18,
                'label' => 'Skilldisplay'
            ]
        ], $statistics->getExpertise());
        self::assertEquals(25, $statistics->getTotalScore());
        self::assertEquals(3, $statistics->getCurrentMonthUsers());
        self::assertEquals(1, $statistics->getLastMonthUsers());
        self::assertEquals(13, $statistics->getCurrentMonthVerifications());
        self::assertEquals(1, $statistics->getLastMonthVerifications());
        self::assertEquals(15, $statistics->getCurrentMonthIssued());
        self::assertEquals(15, $statistics->getSumVerifications());
        self::assertEquals(5, $statistics->getSumSupportedSkills());
        self::assertEquals(5, $statistics->getSumSkills());
        self::assertEquals(0, $statistics->getLastMonthIssued());
        self::assertEquals(16, $statistics->getSumIssued());
        self::assertEquals([
                0 => 9,
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0,
                5 => 0,
                6 => 1,
                7 => 0,
                8 => 0,
                9 => 0,
                10 => 1,
                11 => 13,
                12 => 0,
            ], $statistics->getMonthlyScores());

        self::assertEquals([
                1 => 1,
                2 => 1,
                3 => 1,
            ], $statistics->getInterests());

        self::assertEquals([
                'tier1' => [
                    'total' => 0,
                    'verified' => 0,
                    'potential' => 0
                ],
                'tier2' => [
                    'total' => 5,
                    'verified' => 4,
                    'potential' => 0
                ],
                'tier3' => [
                    'total' => 0,
                    'verified' => 0,
                    'potential' => 0
                ],
                'tier4' => [
                    'total' => 10,
                    'verified' => 6,
                    'potential' => 0
                ]
            ], $statistics->getPotential());

        self::assertEquals([
                'tier3' => 5,
                'tier4' => 6,
                'tier2' => 4,
            ], $statistics->getComposition()['Skilldisplay']);

        foreach ($this->brandRepository->findAll() as $brand) {
            $statistics = $this->organisationStatisticsRepository->getOrganisationStatisticsForBrand($brand->getUid());
            $sum = 0;
            foreach ($statistics->getExpertise() as $value) {
                $sum += $value['points'];
            }
            self::assertEquals($statistics->getTotalScore(), $sum);
        }
    }

    /**
     * @test
     * TODO Check actual statistic results
     */
    public function calculateUserActivity()
    {
        $this->statisticsService->calculateUserActivityStatistics();
    }
}
