<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Service;

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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

class StatisticsServiceTest extends AbstractFunctionalTestCaseBase
{
    protected StatisticsService|MockObject|AccessibleObjectInterface $statisticsService;
    protected OrganisationStatisticsRepository $organisationStatisticsRepository;
    protected BrandRepository $brandRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organisationStatisticsRepository = GeneralUtility::makeInstance(OrganisationStatisticsRepository::class);
        $this->brandRepository = GeneralUtility::makeInstance(BrandRepository::class);

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/fe_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_brand.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_certifier.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_skill.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_certifierpermission.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_skill_brand_mm.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_user_brand_mm.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_user_organisation_mm.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_certification.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_skillpath.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_skillpath_skill_mm.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_skillset_brand_mm.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_skills_domain_model_membershiphistory.csv');

        $this->statisticsService = $this->getAccessibleMock(StatisticsService::class, null, [
            GeneralUtility::makeInstance(BrandRepository::class),
            GeneralUtility::makeInstance(UserRepository::class),
            GeneralUtility::makeInstance(CertifierRepository::class),
            GeneralUtility::makeInstance(CertificationRepository::class),
            GeneralUtility::makeInstance(SkillPathRepository::class),
            GeneralUtility::makeInstance(SkillRepository::class),
            GeneralUtility::makeInstance(PersistenceManager::class),
            GeneralUtility::makeInstance(ConnectionPool::class),
        ]);
    }

    /**
     * @test
     */
    public function runReturnsExpectedAwardCounts(): void
    {
        $this->statisticsService->run();

        /** @var AwardRepository $awardRepo */
        $awardRepo = GeneralUtility::makeInstance(AwardRepository::class);

        $verifiedAwards = $awardRepo->getAwardsByType(Award::TYPE_VERIFICATIONS);
        self::assertCount(21, $verifiedAwards);

        $memberAwards = $awardRepo->getAwardsByType(Award::TYPE_MEMBER);
        self::assertCount(8, $memberAwards);

        $coachAwards = $awardRepo->getAwardsByType(Award::TYPE_COACH);
        self::assertCount(3, $coachAwards);

        $mentorAwards = $awardRepo->getAwardsByType(Award::TYPE_MENTOR);
        self::assertCount(1, $mentorAwards);
    }

    public static function runReturnsCorrectNumberOfAwardsForUserDataProvider(): array
    {
        return [
            'user 1' => [1, 8],
            'user 8' => [8, 3],
        ];
    }

    /**
     * @test
     * @dataProvider runReturnsCorrectNumberOfAwardsForUserDataProvider
     * @param int $userId
     * @param int $expected
     */
    public function runReturnsCorrectNumberOfAwardsForUser(int $userId, int $expected): void
    {
        $this->statisticsService->run();

        /** @var AwardRepository $awardRepo */
        $awardRepo = GeneralUtility::makeInstance(AwardRepository::class);
        $userAwards = $awardRepo->getAwardsByUserId($userId);
        self::assertCount($expected, $userAwards);
    }

    /**
     * @test
     */
    public function calculateOrganisationStatisticsReturnsNumberOfStatistics(): void
    {
        // set time to 1. 8. 2019
        /** @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('date', new DateTimeAspect((new \DateTimeImmutable())->setTimestamp(1564617600)));

        $this->statisticsService->calculateOrganisationStatistics();

        $statistics = $this->organisationStatisticsRepository->getOrganisationStatisticsForBrand(1);

        self::assertSame([
            [
                'points' => 7,
                'label' => 'Erasmus+',
            ],
            [
                'points' => 18,
                'label' => 'Skilldisplay',
            ],
        ], $statistics->getExpertise());
        self::assertSame(25, $statistics->getTotalScore());
        self::assertSame(3, $statistics->getCurrentMonthUsers());
        self::assertSame(1, $statistics->getLastMonthUsers());
        self::assertSame(13, $statistics->getCurrentMonthVerifications());
        self::assertSame(1, $statistics->getLastMonthVerifications());
        self::assertSame(15, $statistics->getCurrentMonthIssued());
        self::assertSame(15, $statistics->getSumVerifications());
        self::assertSame(5, $statistics->getSumSupportedSkills());
        self::assertSame(5, $statistics->getSumSkills());
        self::assertSame(0, $statistics->getLastMonthIssued());
        self::assertSame(16, $statistics->getSumIssued());
        self::assertSame([
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

        self::assertSame([
            3 => 1,
            1 => 1,
            2 => 1,
        ], $statistics->getInterests());

        self::assertSame([
            'tier1' => [
                'total' => 0,
                'verified' => 0,
                'potential' => 0,
            ],
            'tier2' => [
                'total' => 5,
                'verified' => 4,
                'potential' => 0,
            ],
            'tier3' => [
                'total' => 0,
                'verified' => 0,
                'potential' => 0,
            ],
            'tier4' => [
                'total' => 10,
                'verified' => 6,
                'potential' => 0,
            ],
        ], $statistics->getPotential());

        self::assertSame([
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
            self::assertSame($statistics->getTotalScore(), $sum);
        }
    }
}
