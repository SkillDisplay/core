<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Service;

use Doctrine\DBAL\DBALException;
use LogicException;
use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Model\Skill;
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
use TYPO3\TestingFramework\Core\Exception;

class VerificationServiceTest extends AbstractFunctionalTestCaseBase
{
    /** @var VerificationService */
    protected $verificationService;

    /** @var SkillRepository */
    protected $skillRepository;

    /** @var SkillPathRepository */
    protected $skillSetRepository;

    /** @var UserRepository */
    protected $userRepository;

    /** @var CertifierRepository */
    protected $certifierRepository;

    /** @var CertificationRepository */
    protected $verificationRepository;

    /** @var BrandRepository */
    protected $brandRepository;

    /** @var VerificationCreditUsageRepository */
    protected $verificationCreditUsageRepository;


    /**
     * @throws DBALException
     * @throws Exception
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->verificationService = $this->objectManager->get(VerificationService::class);
        $this->verificationService->disableCertoBot();
        $this->verificationService->setCreditSettings([
            'price' => 0.45,
            'tier1' => 4,
            'tier2' => 4,
            'tier3' => 0,
            'tier4' => 4,
        ]);
        $this->skillRepository = $this->objectManager->get(SkillRepository::class);
        $this->skillSetRepository = $this->objectManager->get(SkillPathRepository::class);
        $this->userRepository = $this->objectManager->get(UserRepository::class);
        $this->certifierRepository = $this->objectManager->get(CertifierRepository::class);
        $this->verificationRepository = $this->objectManager->get(CertificationRepository::class);
        $this->brandRepository = $this->objectManager->get(BrandRepository::class);
        $this->verificationCreditUsageRepository = $this->objectManager->get(VerificationCreditUsageRepository::class);

        $this->importDataSet(__DIR__ . '/../Fixtures/user_access_test.xml');
        $this->importDataSet(__DIR__ . '/../Fixtures/confirm_skillup_test.xml');
    }

    /**
     * @test
     */
    public function skillUpWorksForVisibleSkills()
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        $skills = [$skill];
        $skillSet = $this->skillSetRepository->findByUid(1);

        $user = $this->userRepository->findByUsername('muster');
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $autoConfirm = false;
        $tier = 1;

        $result = $this->verificationService->handleSkillUpRequest($skills, $skillSet->getSkillGroupId(), $user, $tier, '', $verifier, null, $autoConfirm);

        $this->assertCount(0, $result['failedSkills']);
        $this->assertCount(1, $result['verifications']);
    }

    /**
     * @test
     */
    public function skillUpChecksForVisibilityOfSkills()
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        $skills = [$skill];
        $skillSet = $this->skillSetRepository->findByUid(1);

        $user = $this->userRepository->findByUsername('muster2');
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $autoConfirm = false;
        $tier = 1;

        $result = $this->verificationService->handleSkillUpRequest($skills, $skillSet->getSkillGroupId(), $user, $tier, '', $verifier, null, $autoConfirm);

        $this->assertCount(1, $result['failedSkills']);
        $this->assertCount(0, $result['verifications']);
    }

    /**
     * @test
     */
    public function skillUpWorksForVisibleSkill()
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);

        $user = $this->userRepository->findByUsername('muster');
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $autoConfirm = false;
        $tier = 1;

        $result = $this->verificationService->handleSkillUpRequest([$skill], '', $user, $tier, '', $verifier, null, $autoConfirm);

        $this->assertCount(0, $result['failedSkills']);
        $this->assertCount(1, $result['verifications']);
    }

    /**
     * @test
     */
    public function skillUpChecksForVisibilityOfSkill()
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);

        $user = $this->userRepository->findByUsername('muster2');
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $autoConfirm = false;
        $tier = 1;

        $result = $this->verificationService->handleSkillUpRequest([$skill], '', $user, $tier, '', $verifier, null, $autoConfirm);

        $this->assertCount(1, $result['failedSkills']);
        $this->assertCount(0, $result['verifications']);
    }

    /**
     * @test
     */
    public function confirmSkillUpChecksForCorrectValuesOneVerification()
    {
        $this->verificationService->confirmSkillUp($this->verificationRepository->findByUid(1000), true);
        $usages = $this->verificationCreditUsageRepository->findAll()->toArray();
        $this->assertCount(1, $usages);
        /** @var VerificationCreditUsage $usage */
        $usage = $usages[0];
        $this->assertEquals(6, $usage->getCreditPack()->getCurrentPoints());
        /** @var Certification $verification */
        $verification = $this->verificationRepository->findByUid(1000);
        $this->assertEquals(4, $verification->getPoints());
        $this->assertEquals(0.45, $verification->getPrice());
    }

    /**
     * @test
     */
    public function confirmSkillUpChecksForCorrectValuesTwoVerifications()
    {
        $this->verificationService->confirmSkillUp($this->verificationRepository->findByUid(1000), true);
        $this->verificationService->confirmSkillUp($this->verificationRepository->findByUid(1001), true);
        $usages = $this->verificationCreditUsageRepository->findAll()->toArray();
        $this->assertCount(2, $usages);
        /** @var VerificationCreditUsage $usage */
        $usage = $usages[0];
        $this->assertEquals(2, $usage->getCreditPack()->getCurrentPoints());
        /** @var Certification $verification */
        $verification1 = $this->verificationRepository->findByUid(1000);
        /** @var Certification $verification */
        $verification2 = $this->verificationRepository->findByUid(1001);
        $this->assertEquals(4, $verification2->getPoints());
        $this->assertEquals(0.45, $verification2->getPrice());
    }

    /**
     * @test
     */
    public function confirmSkillUpThrowsExceptionWhenCreditsDoNotSuffice()
    {
        $this->expectException(LogicException::class);
        $this->verificationService->confirmSkillUp($this->verificationRepository->findByUid(1000), true);
        $this->verificationService->confirmSkillUp($this->verificationRepository->findByUid(1001), true);
        $this->verificationService->confirmSkillUp($this->verificationRepository->findByUid(1002), true);
    }

    /**
     * @test
     */
    public function confirmSkillUpThrowsExceptionWhenNoPacksAndOverdrawDisabled()
    {
        $this->expectException(LogicException::class);
        $this->verificationService->confirmSkillUp($this->verificationRepository->findByUid(1003), true);
    }

    /**
     * @test
     */
    public function confirmSkillUpNoPackAndOverdrawEnabledSetsCorrectVerificationValues()
    {
        /** @var Brand $brand */
        $brand = $this->brandRepository->findByUid(1001);
        $brand->setCreditOverdraw(true);
        $this->verificationService->confirmSkillUp($this->verificationRepository->findByUid(1003), true);
        $usages = $this->verificationCreditUsageRepository->findAll()->toArray();
        $this->assertCount(0, $usages);
        /** @var Certification $verification */
        $verification = $this->verificationRepository->findByUid(1003);
        $this->assertEquals(4, $verification->getPoints());
        $this->assertEquals(0.45, $verification->getPrice());
    }
}
