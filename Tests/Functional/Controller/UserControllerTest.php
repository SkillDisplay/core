<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Controller;

use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\MockObject\MockObject;
use SJBR\StaticInfoTables\Domain\Repository\CountryRepository;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Controller\UserController;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Repository\AwardRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Service\ShortLinkService;
use SkillDisplay\Skills\Service\UserService;
use SkillDisplay\Skills\Validation\Validator\EditUserValidator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Repository\FrontendUserGroupRepository;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Exception;

/**
 * Test case.
 *
 * @author Markus Klein <markus.klein@reelworx.at>
 */
class UserControllerTest extends AbstractFunctionalControllerTestCaseBase
{
    /** @var UserController|MockObject|AccessibleObjectInterface */
    protected $subject = null;

    /** @var UserService|MockObject */
    protected $userManager;

    /**
     * @throws DBALException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        // disable file processing for tests
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors']['DeferredBackendImageProcessor']);

        $this->userManager = $this->getMockBuilder(UserService::class)->setConstructorArgs([
            $this->objectManager->get(FrontendUserGroupRepository::class),
            $this->userRepository,
        ])->getMock();

        $validator = $this->getMockBuilder(EditUserValidator::class)
                          ->onlyMethods(['getErrorMessage'])
                          ->setConstructorArgs([[], $this->userRepository])
                          ->getMock();
        $validator->method('getErrorMessage')->willReturn('');
        GeneralUtility::addInstance(EditUserValidator::class, $validator);

        $this->subject = $this->getAccessibleMock(UserController::class,
            ['redirect', 'forward', 'addFlashMessage', 'getCurrentUser'], [
                $this->objectManager->get(SkillRepository::class),
                $this->userManager,
                $this->userRepository,
                $this->objectManager->get(ShortLinkService::class),
                $this->objectManager->get(CertifierRepository::class),
                $this->objectManager->get(CountryRepository::class),
                $this->objectManager->get(AwardRepository::class),
                $this->objectManager->get(CertificationRepository::class),
            ]);
        $this->subject->injectObjectManager($this->objectManager);
        $this->subject->_set('view', $this->view);
    }

    /**
     * @throws Exception
     */
    protected function setUpDatabase(): void
    {
        $this->setUpBackendUserFromFixture(1);
        $this->importDataSet(__DIR__ . '/../Fixtures/user_access_test.xml');
    }

    /**
     * @test
     */
    public function updateProfileFailsForNoLoggedInUser()
    {
        $this->expectException(\RuntimeException::class);

        $this->subject->updateProfileAction('', '', '', '', '', '', '', '');
    }

    /**
     * @test
     */
    public function updateProfileFailsForNoEmptyFirstName()
    {
        $this->simulateLogin();
        $this->userManager->expects(self::never())->method('update');

        $this->subject->updateProfileAction('', 'asdf', '', '', '', '', '', '');

        $success = $this->view->_get('variables')['success'];
        self::assertEquals(false, $success['status']);
    }

    /**
     * @test
     */
    public function updateProfileFailsForNoEmptyLastName()
    {
        $this->simulateLogin();
        $this->userManager->expects(self::never())->method('update');

        $this->subject->updateProfileAction('first', '', '', '', '', '', '', '');

        $success = $this->view->_get('variables')['success'];
        self::assertEquals(false, $success['status']);
    }

    /**
     * @test
     */
    public function updateProfileSuccessForValidName()
    {
        $this->simulateLogin();
        $this->userManager->expects(self::once())->method('update')->with($this->currentUser);

        $this->subject->updateProfileAction('first', 'last', '', '', '', '', '', '');

        $success = $this->view->_get('variables')['success'];
        self::assertEquals(true, $success['status']);

        $updatedUser = $this->userRepository->findByUsername('muster');
        $this->assertEquals('first', $updatedUser->getFirstName());
        $this->assertEquals('last', $updatedUser->getLastName());
    }

    /**
     * @test
     */
    public function publicProfileNotVisibleIfNotEnabled()
    {
        $this->currentUser->setPublishSkills(false);
        $this->subject->publicProfileAction($this->currentUser);
        $profile = $this->view->_get('variables')['publicProfile'];

        self::assertEquals('The requested user does not want his profile to be published publicly.',
            $profile['message']);
    }

    /**
     * @test
     */
    public function publicVerificationsHiddenIfUserDoesNotPublish()
    {
        $this->expectException(AuthenticationException::class);

        $this->simulateLogin2();
        $this->currentUser->setPublishSkills(false);
        $this->subject->publicProfileVerificationsAction($this->currentUser);
    }

    /**
     * @test
     */
    public function publicVerificationsByBrandHiddenIfUserDoesNotPublish()
    {
        $this->expectException(AuthenticationException::class);

        $this->simulateLogin2();
        $this->currentUser->setPublishSkills(false);
        $this->subject->publicProfileVerificationsAction($this->currentUser, Certification::TYPE_GROUPED_BY_BRAND);
    }

    /**
     * @test
     */
    public function publicVerificationsHidePrivateSkills()
    {
        $this->simulateLogin2();
        $this->subject->publicProfileVerificationsAction($this->currentUser, Certification::TYPE_GROUPED_BY_BRAND);
        $brands = $this->view->_get('variables')['brands'];

        self::assertCount(1, $brands[0]['tags'][0]['skills']);
    }

    /**
     * @test
     */
    public function publicVerificationsShowsPrivateSkillsForMembers()
    {
        $this->simulateLogin();
        $this->subject->publicProfileVerificationsAction($this->currentUser, Certification::TYPE_GROUPED_BY_BRAND);
        $brands = $this->view->_get('variables')['brands'];

        self::assertCount(2, $brands[0]['tags'][0]['skills']);
    }

    /**
     * @test
     */
    public function publicVerificationsHidePrivateSkillsForDateType()
    {
        $this->simulateLogin2();
        $this->subject->publicProfileVerificationsAction($this->currentUser);
        $verifications = $this->view->_get('variables')['verifications'];

        self::assertCount(1, $verifications);
    }

    /**
     * @test
     */
    public function publicVerificationsShowsPrivateSkillsForMembersForDateType()
    {
        $this->simulateLogin();
        $this->subject->publicProfileVerificationsAction($this->currentUser);
        $verifications = $this->view->_get('variables')['verifications'];

        self::assertCount(2, $verifications);
    }
}
