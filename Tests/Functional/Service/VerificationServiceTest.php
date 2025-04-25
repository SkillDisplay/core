<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Service;

use LogicException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Model\VerificationCreditUsage;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditUsageRepository;
use SkillDisplay\Skills\Service\VerificationService;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class VerificationServiceTest extends AbstractFunctionalTestCaseBase
{
    protected VerificationService $verificationService;
    protected SkillRepository $skillRepository;
    protected SkillPathRepository $skillSetRepository;
    protected UserRepository $userRepository;
    protected CertifierRepository $certifierRepository;
    protected CertificationRepository $verificationRepository;
    protected BrandRepository $brandRepository;
    protected VerificationCreditUsageRepository $verificationCreditUsageRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verificationService = GeneralUtility::makeInstance(VerificationService::class);
        $this->verificationService->disableCertoBot();
        $this->verificationService->setCreditSettings([
            'price' => 0.45,
            'tier1' => 4,
            'tier2' => 4,
            'tier3' => 0,
            'tier4' => 4,
        ]);
        $this->skillRepository = GeneralUtility::makeInstance(SkillRepository::class);
        $this->skillSetRepository = GeneralUtility::makeInstance(SkillPathRepository::class);
        $this->userRepository = GeneralUtility::makeInstance(UserRepository::class);
        $this->certifierRepository = GeneralUtility::makeInstance(CertifierRepository::class);
        $this->verificationRepository = GeneralUtility::makeInstance(CertificationRepository::class);
        $this->brandRepository = GeneralUtility::makeInstance(BrandRepository::class);
        $this->verificationCreditUsageRepository = GeneralUtility::makeInstance(
            VerificationCreditUsageRepository::class
        );

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/user_access_test.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/confirm_skillup_test.csv');
    }

    /**
     * @test
     */
    public function skillUpWorksForVisibleSkills(): void
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        $skills = [$skill];
        /** @var SkillPath $skillSet */
        $skillSet = $this->skillSetRepository->findByUid(1);

        /** @var User $user */
        $user = $this->userRepository->findByUsername('muster');
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $autoConfirm = false;
        $tier = 1;

        $result = $this->verificationService->handleSkillUpRequest(
            $skills,
            $skillSet->getSkillGroupId(),
            $user,
            $tier,
            '',
            $verifier,
            null,
            $autoConfirm
        );

        self::assertCount(0, $result['failedSkills']);
        self::assertCount(1, $result['verifications']);
    }

    /**
     * @test
     */
    public function skillUpChecksForVisibilityOfSkills(): void
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        $skills = [$skill];
        /** @var SkillPath $skillSet */
        $skillSet = $this->skillSetRepository->findByUid(1);

        $user = $this->userRepository->findByUsername('muster2');
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $autoConfirm = false;
        $tier = 1;

        $result = $this->verificationService->handleSkillUpRequest(
            $skills,
            $skillSet->getSkillGroupId(),
            $user,
            $tier,
            '',
            $verifier,
            null,
            $autoConfirm
        );

        self::assertCount(1, $result['failedSkills']);
        self::assertCount(0, $result['verifications']);
    }

    /**
     * @test
     */
    public function skillUpWorksForVisibleSkill(): void
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);

        $user = $this->userRepository->findByUsername('muster');
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $autoConfirm = false;
        $tier = 1;

        $result = $this->verificationService->handleSkillUpRequest(
            [$skill],
            '',
            $user,
            $tier,
            '',
            $verifier,
            null,
            $autoConfirm
        );

        self::assertCount(0, $result['failedSkills']);
        self::assertCount(1, $result['verifications']);
    }

    /**
     * @test
     */
    public function skillUpChecksForVisibilityOfSkill(): void
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);

        $user = $this->userRepository->findByUsername('muster2');
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $autoConfirm = false;
        $tier = 1;

        $result = $this->verificationService->handleSkillUpRequest(
            [$skill],
            '',
            $user,
            $tier,
            '',
            $verifier,
            null,
            $autoConfirm
        );

        self::assertCount(1, $result['failedSkills']);
        self::assertCount(0, $result['verifications']);
    }

    /**
     * @test
     */
    public function confirmSkillUpChecksForCorrectValuesOneVerification(): void
    {
        /** @var Certification $verification */
        $verification = $this->verificationRepository->findByUid(1000);
        $this->verificationService->confirmSkillUp($verification, true);
        $usages = $this->verificationCreditUsageRepository->findAll()->toArray();
        self::assertCount(1, $usages);
        /** @var VerificationCreditUsage $usage */
        $usage = $usages[0];
        self::assertSame(6, $usage->getCreditPack()->getCurrentPoints());
        /** @var Certification $verification */
        $verification = $this->verificationRepository->findByUid(1000);
        self::assertSame(4, $verification->getPoints());
        self::assertSame(0.45, $verification->getPrice());
    }

    /**
     * @test
     */
    public function confirmSkillUpChecksForCorrectValuesTwoVerifications(): void
    {
        /** @var Certification $verification1 */
        $verification1 = $this->verificationRepository->findByUid(1000);
        /** @var Certification $verification2 */
        $verification2 = $this->verificationRepository->findByUid(1001);
        $this->verificationService->confirmSkillUp($verification1, true);
        $this->verificationService->confirmSkillUp($verification2, true);
        $usages = $this->verificationCreditUsageRepository->findAll()->toArray();
        self::assertCount(2, $usages);
        /** @var VerificationCreditUsage $usage */
        $usage = $usages[0];
        self::assertSame(2, $usage->getCreditPack()->getCurrentPoints());
        /** @var Certification $verification2 */
        $verification2 = $this->verificationRepository->findByUid(1001);
        self::assertSame(4, $verification2->getPoints());
        self::assertSame(0.45, $verification2->getPrice());
    }

    /**
     * @test
     */
    public function confirmSkillUpThrowsExceptionWhenCreditsDoNotSuffice(): void
    {
        $this->expectException(LogicException::class);
        /** @var Certification $verification1 */
        $verification1 = $this->verificationRepository->findByUid(1000);
        /** @var Certification $verification2 */
        $verification2 = $this->verificationRepository->findByUid(1001);
        /** @var Certification $verification3 */
        $verification3 = $this->verificationRepository->findByUid(1002);
        $this->verificationService->confirmSkillUp($verification1, true);
        $this->verificationService->confirmSkillUp($verification2, true);
        $this->verificationService->confirmSkillUp($verification3, true);
    }

    /**
     * @test
     */
    public function confirmSkillUpThrowsExceptionWhenNoPacksAndOverdrawDisabled(): void
    {
        $this->expectException(LogicException::class);
        /** @var Certification $verification4 */
        $verification4 = $this->verificationRepository->findByUid(1003);
        $this->verificationService->confirmSkillUp($verification4, true);
    }

    /**
     * @test
     */
    public function confirmSkillUpNoPackAndOverdrawEnabledSetsCorrectVerificationValues(): void
    {
        /** @var Brand $brand */
        $brand = $this->brandRepository->findByUid(1001);
        /** @var Certification $verification4 */
        $verification4 = $this->verificationRepository->findByUid(1003);

        $brand->setCreditOverdraw(true);
        $this->verificationService->confirmSkillUp($verification4, true);
        $usages = $this->verificationCreditUsageRepository->findAll()->toArray();
        self::assertCount(0, $usages);
        /** @var Certification $verification */
        $verification = $this->verificationRepository->findByUid(1003);
        self::assertSame(4, $verification->getPoints());
        self::assertSame(0.45, $verification->getPrice());
    }

    /**
     * @test
     */
    public function moveVerificationsCreatesVerifications(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/skillsplitting.csv');

        /** @var Skill $source */
        $source = $this->skillRepository->findByUid(6);
        /** @var Skill $target1 */
        $target1 = $this->skillRepository->findByUid(7);
        /** @var Skill $target2 */
        $target2 = $this->skillRepository->findByUid(8);

        $this->verificationService->moveVerifications($source, [$target1, $target2]);
        GeneralUtility::makeInstance(PersistenceManager::class)->persistAll();

        self::assertCount(0, $this->verificationRepository->findBySkill($source), 'source verifications');
        self::assertCount(2, $this->verificationRepository->findBySkill($target1), 'target1 verifications');
        self::assertCount(2, $this->verificationRepository->findBySkill($target2), 'target2 verifications');
    }
}
