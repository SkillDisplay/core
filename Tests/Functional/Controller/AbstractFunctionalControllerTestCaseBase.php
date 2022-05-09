<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

abstract class AbstractFunctionalControllerTestCaseBase extends AbstractFunctionalTestCaseBase
{
    /** @var MockObject|AccessibleObjectInterface */
    protected $subject = null;

    protected ?User $currentUser;
    protected UserRepository $userRepository;

    /** @var JsonView|MockObject|AccessibleObjectInterface  */
    protected $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
        $this->userRepository = $this->objectManager->get(UserRepository::class);
        $this->currentUser = $this->userRepository->findByUsername('muster');
        $this->view = $this->getAccessibleMock(JsonView::class, ['dummy']);
    }

    protected function setUpDatabase(): void
    {
    }

    protected function simulateLogin()
    {
        if ($this->subject) {
            $this->subject->expects($this->any())->method('getCurrentUser')->will($this->returnValue($this->currentUser));
        }
    }

    protected function simulateLogin2()
    {
        if ($this->subject) {
            $user = $this->userRepository->findByUsername('muster2');
            $this->subject->expects($this->any())
                          ->method('getCurrentUser')
                          ->will($this->returnValue($user));
        }
    }
}
