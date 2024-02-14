<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Controller\CertificationController;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\CampaignRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditPackRepository;
use SkillDisplay\Skills\Domain\Repository\VerificationCreditUsageRepository;
use SkillDisplay\Skills\Service\VerificationService;
use SkillDisplay\Skills\Tests\Functional\SimulateLoginTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Exception;

class CertificationControllerTest extends AbstractFunctionalControllerTestCaseBase
{
    use SimulateLoginTrait;

    protected CertificationController|MockObject|AccessibleObjectInterface $subject;

    protected CertificationRepository $certificationRepository;
    protected CertifierRepository $certifierRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->certificationRepository = GeneralUtility::makeInstance(CertificationRepository::class);
        $this->certifierRepository = GeneralUtility::makeInstance(CertifierRepository::class);

        $this->subject = $this->getAccessibleMock(
            CertificationController::class,
            ['addFlashMessage', 'getCurrentUser'],
            [
                $this->userRepository,
                GeneralUtility::makeInstance(CertifierRepository::class),
                $this->certificationRepository,
                GeneralUtility::makeInstance(BrandRepository::class),
                GeneralUtility::makeInstance(VerificationCreditPackRepository::class),
                GeneralUtility::makeInstance(VerificationCreditUsageRepository::class),
                GeneralUtility::makeInstance(SkillPathRepository::class),
                GeneralUtility::makeInstance(SkillRepository::class),
                GeneralUtility::makeInstance(CampaignRepository::class),
                GeneralUtility::makeInstance(VerificationService::class),
            ]
        );
        $this->subject->_set('settings', ['credits' => [
            'price' => 0.1,
            'tier4' => 2,
            'tier3' => 0,
            'tier2' => 3,
            'tier1' => 4,
        ]]);
        $this->initController($this->subject);
    }

    /**
     * @throws Exception
     */
    protected function setUpDatabase(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $this->importDataSet(__DIR__ . '/../Fixtures/user_access_test.xml');
    }

    /**
     * @test
     */
    public function showShowsCertificationForUser()
    {
        $this->simulateLogin();
        /** @var Certification $certification */
        $certification = $this->certificationRepository->findByUid(1);
        $this->subject->showAction($certification);

        $verification = $this->view->_get('variables')['verification'];

        self::assertSame(1, $verification['uid']);
    }

    /**
     * @test
     */
    public function doesShowOnlyPublicInformationForForeignUser()
    {
        $this->simulateLogin();
        /** @var Certification $certification */
        $certification = $this->certificationRepository->findByUid(3);
        $this->subject->showAction($certification);
        $verification = $this->view->_get('variables')['verification'];

        self::assertNull($verification['verifier']);
        self::assertEmpty($verification['reason']);
        self::assertEmpty($verification['comment']);
    }

    /**
     * @test
     */
    public function modifyModifiesCertificationForCertifier()
    {
        $this->simulateLogin();
        $this->subject->modifyAction([4], true);
        $success = $this->view->_get('variables')['success'];
        $error = $this->view->_get('variables')['error'];

        self::assertSame('', $error);
        self::assertTrue($success);
    }

    /**
     * @test
     */
    public function modifyDoesNotModifyCertificationForGuest()
    {
        $this->simulateLogin2();
        $this->subject->modifyAction([4], true);
        $success = $this->view->_get('variables')['success'];
        $error = $this->view->_get('variables')['error'];

        self::assertNotEquals('', $error);
        self::assertFalse($success);
    }

    /**
     * @test
     */
    public function modifyDoesNotModifyCertificationForUser()
    {
        $this->simulateLogin2();
        $this->subject->modifyAction([4], true);
        $success = $this->view->_get('variables')['success'];
        $error = $this->view->_get('variables')['error'];

        self::assertNotEquals('', $error);
        self::assertFalse($success);
    }

    /**
     * @test
     */
    public function cancelCertificationForUser()
    {
        $this->simulateLogin2();
        $this->subject->userCancelAction([4]);
        $success = $this->view->_get('variables')['success'];

        self::assertTrue($success);
    }

    /**
     * @test
     */
    public function doNotCancelCertificationForForeignUser()
    {
        $this->simulateLogin();
        $this->subject->userCancelAction([4]);
        $success = $this->view->_get('variables')['success'];

        self::assertFalse($success);
    }

    /**
     * @test
     */
    public function doNotCancelCertificationForGuest()
    {
        $this->simulateLogin();
        $this->subject->userCancelAction([4]);
        $success = $this->view->_get('variables')['success'];

        self::assertFalse($success);
    }

    /**
     * @test
     */
    public function recentShowsNoDataForGuest()
    {
        $this->subject->recentAction();
        $verifications = $this->view->_get('variables')['verifications'];

        self::assertSame([], $verifications);
    }

    /**
     * @test
     */
    public function recentShowsDataForUser()
    {
        $this->simulateLogin();
        $this->subject->recentAction();
        $verifications = $this->view->_get('variables')['verifications'];

        self::assertCount(2, $verifications);
    }

    /**
     * @test
     */
    public function listForVerifierShowsNothingForWrongUser()
    {
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $this->simulateLogin2();
        $this->subject->listForVerifierAction($verifier);
        $verifications = $this->view->_get('variables')['verifications'];

        self::assertSame([], $verifications);
    }

    /**
     * @test
     */
    public function listForVerifierShowsNothingForGuest()
    {
        $this->simulateLogin2();
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $this->subject->listForVerifierAction($verifier);
        $verifications = $this->view->_get('variables')['verifications'];

        self::assertSame([], $verifications);
    }

    /**
     * @test
     */
    public function listForVerifierShowsDataForCorrectUser()
    {
        /** @var Certifier $verifier */
        $verifier = $this->certifierRepository->findByUid(1);
        $this->simulateLogin();
        $this->subject->listForVerifierAction($verifier);
        $verifications = $this->view->_get('variables')['verifications'];

        self::assertCount(1, $verifications);
    }
}
