<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Controller;

use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Controller\SkillController;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Repository\RecommendedSkillSetRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Extbase\Mvc\Response;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Exception;

class SkillControllerTest extends AbstractFunctionalControllerTestCaseBase
{
    /** @var SkillController|MockObject|AccessibleObjectInterface */
    protected $subject = null;

    /** @var Response */
    protected $response = null;

    /** @var SkillRepository */
    protected $skillRepository;

    /**
     * @throws DBALException
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->skillRepository = $this->objectManager->get(SkillRepository::class);
        $this->response = $this->getMockBuilder(Response::class)->getMock();
        $this->subject = $this->getAccessibleMock(
            SkillController::class,
            ['redirect', 'forward', 'addFlashMessage', 'getCurrentUser'],
            [
                $this->skillRepository,
                $this->objectManager->get(RecommendedSkillSetRepository::class),
            ]
        );
        $this->subject->injectObjectManager($this->objectManager);
        $this->subject->_set('view', $this->view);
        $this->subject->_set('response', $this->response);
    }

    /**
     * @throws Exception
     */
    protected function setUpDatabase(): void
    {
        $this->importDataSet(__DIR__ . '/../Fixtures/user_access_test.xml');
    }

    /**
     * @test
     * @throws ImmediateResponseException
     * @throws PageNotFoundException
     */
    public function showsPublicSkill()
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(2);
        $this->subject->showAction($skill);
        /** @var Skill $assignedSkill */
        $assignedSkill = $this->view->_get('variables')['skill'];

        self::assertEquals($skill->getUid(), $assignedSkill->getUid());
    }

    /**
     * @test
     * @throws ImmediateResponseException
     * @throws PageNotFoundException
     */
    public function hidesInternalSkill()
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        $this->subject->showAction($skill);

        /** @var Skill $assignedSkill */
        self::assertEquals(null, $this->view->_get('variables')['skill']);
    }

    /**
     * @test
     * @throws ImmediateResponseException
     * @throws PageNotFoundException
     */
    public function showsInternalSkillForMember()
    {
        $this->simulateLogin();
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        $this->subject->showAction($skill);
        /** @var Skill $assignedSkill */
        $assignedSkill = $this->view->_get('variables')['skill'];

        self::assertEquals($skill->getUid(), $assignedSkill->getUid());
    }
}
