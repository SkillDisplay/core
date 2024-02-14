<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use SJBR\StaticInfoTables\Domain\Repository\CountryRepository;
use SkillDisplay\Skills\AuthenticationException;
use SkillDisplay\Skills\Controller\UserController;
use SkillDisplay\Skills\Domain\Model\Certification;
use SkillDisplay\Skills\Domain\Repository\AwardRepository;
use SkillDisplay\Skills\Domain\Repository\CertificationRepository;
use SkillDisplay\Skills\Domain\Repository\CertifierRepository;
use SkillDisplay\Skills\Domain\Repository\GrantedRewardRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Service\ShortLinkService;
use SkillDisplay\Skills\Service\UserService;
use SkillDisplay\Skills\Tests\Functional\SimulateLoginTrait;
use SkillDisplay\Skills\Validation\Validator\EditUserValidator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Repository\FrontendUserGroupRepository;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Exception;

class UserControllerTest extends AbstractFunctionalControllerTestCaseBase
{
    use SimulateLoginTrait;

    protected UserController|MockObject|AccessibleObjectInterface $subject;

    protected MockObject|UserService $userManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->getMockBuilder(UserService::class)->setConstructorArgs([
            GeneralUtility::makeInstance(FrontendUserGroupRepository::class),
            $this->userRepository,
        ])->getMock();

        $validator = $this->getMockBuilder(EditUserValidator::class)
                          ->onlyMethods(['getErrorMessage'])
                          ->setConstructorArgs([[], $this->userRepository])
                          ->getMock();
        $validator->method('getErrorMessage')->willReturn('');
        GeneralUtility::addInstance(EditUserValidator::class, $validator);

        $this->subject = $this->getAccessibleMock(
            UserController::class,
            ['addFlashMessage', 'getCurrentUser'],
            [
                $this->userRepository,
                GeneralUtility::makeInstance(SkillRepository::class),
                $this->userManager,
                GeneralUtility::makeInstance(ShortLinkService::class),
                GeneralUtility::makeInstance(CertifierRepository::class),
                GeneralUtility::makeInstance(CountryRepository::class),
                GeneralUtility::makeInstance(AwardRepository::class),
                GeneralUtility::makeInstance(CertificationRepository::class),
                GeneralUtility::makeInstance(GrantedRewardRepository::class),
            ]
        );
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
    public function updateProfileFailsForNoLoggedInUser()
    {
        $this->expectException(RuntimeException::class);

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
        self::assertFalse($success['status']);
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
        self::assertFalse($success['status']);
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
        self::assertTrue($success['status']);

        $updatedUser = $this->userRepository->findByUsername('muster');
        self::assertSame('first', $updatedUser->getFirstName());
        self::assertSame('last', $updatedUser->getLastName());
    }

    /**
     * @test
     * @throws InvalidQueryException
     */
    public function publicProfileNotVisibleIfNotEnabled()
    {
        $this->currentUser->setPublishSkills(false);
        $this->subject->publicProfileAction($this->currentUser);
        $profile = $this->view->_get('variables')['publicProfile'];

        self::assertSame(
            'The requested user does not want his profile to be published publicly.',
            $profile['message']
        );
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
