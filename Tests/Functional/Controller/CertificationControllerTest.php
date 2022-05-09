<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Controller;

use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Controller\CertificationController;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Model\Certifier;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

class CertificationControllerTest extends AbstractFunctionalControllerTestCaseBase
{
    /** @var CertificationController|MockObject|AccessibleObjectInterface */
    protected $subject = null;

    /** @var CertificationRepository */
    protected $certificationRepository;

    /** @var CertifierRepository */
    protected $certifierRepository;

    /**
     * @throws DBALException
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        // disable file processing for tests
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']['DeferredBackendImageProcessor']);

        $this->certificationRepository = $this->objectManager->get(CertificationRepository::class);
        $this->certifierRepository = $this->objectManager->get(CertifierRepository::class);

        $this->subject = $this->getAccessibleMock(CertificationController::class,
            ['redirect', 'forward', 'addFlashMessage', 'getCurrentUser'], [$this->certificationRepository]);
        $this->subject->injectObjectManager($this->objectManager);
        $this->subject->_set('view', $this->view);
        $this->subject->_set('settings', ['credits' => [
            'price' => 0.1,
            'tier4' => 2,
            'tier3' => 0,
            'tier2' => 3,
            'tier1' => 4,
        ]]);
    }

    /**
     * @throws \TYPO3\TestingFramework\Core\Exception
     */
    protected function setUpDatabase(): void
    {
        $this->setUpBackendUserFromFixture(1);
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

        $this->assertEquals(1, $verification['uid']);
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

        $this->assertNull($verification['verifier']);
        $this->assertEmpty($verification['reason']);
        $this->assertEmpty($verification['comment']);
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

        $this->assertEquals('', $error);
        $this->assertEquals(true, $success);
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

        $this->assertNotEquals('', $error);
        $this->assertEquals(false, $success);
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

        $this->assertNotEquals('', $error);
        $this->assertEquals(false, $success);
    }

    /**
     * @test
     */
    public function cancelCertificationForUser()
    {
        $this->simulateLogin2();
        $this->subject->userCancelAction([4]);
        $success = $this->view->_get('variables')['success'];

        $this->assertEquals(true, $success);
    }

    /**
     * @test
     */
    public function doNotCancelCertificationForForeignUser()
    {
        $this->simulateLogin();
        $this->subject->userCancelAction([4]);
        $success = $this->view->_get('variables')['success'];

        $this->assertEquals(false, $success);
    }

    /**
     * @test
     */
    public function doNotCancelCertificationForGuest()
    {
        $this->simulateLogin();
        $this->subject->userCancelAction([4]);
        $success = $this->view->_get('variables')['success'];

        $this->assertEquals(false, $success);
    }

    /**
     * @test
     */
    public function recentShowsNoDataForGuest()
    {
        $this->subject->recentAction();
        $verifications = $this->view->_get('variables')['verifications'];

        $this->assertEquals([], $verifications);
    }

    /**
     * @test
     */
    public function recentShowsDataForUser()
    {
        $this->simulateLogin();
        $this->subject->recentAction();
        $verifications = $this->view->_get('variables')['verifications'];

        $this->assertCount(2, $verifications);
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

        $this->assertEquals([], $verifications);
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

        $this->assertEquals([], $verifications);
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

        $this->assertCount(1, $verifications);
    }
}
