<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Controller\SkillController;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Repository\RecommendedSkillSetRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Service\VerificationService;
use SkillDisplay\Skills\Tests\Functional\SimulateLoginTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

class SkillControllerTest extends AbstractFunctionalControllerTestCaseBase
{
    use SimulateLoginTrait;

    protected SkillController&MockObject&AccessibleObjectInterface $subject;

    protected SkillRepository $skillRepository;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->skillRepository = GeneralUtility::makeInstance(SkillRepository::class);
        $this->subject = $this->getAccessibleMock(
            SkillController::class,
            ['addFlashMessage', 'getCurrentUser'],
            [
                $this->userRepository,
                $this->skillRepository,
                GeneralUtility::makeInstance(RecommendedSkillSetRepository::class),
                GeneralUtility::makeInstance(VerificationService::class),
            ]
        );
        $this->initController($this->subject);
    }

    #[\Override]
    protected function setUpDatabase(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/user_access_test.csv');
    }

    /**
     * @test
     */
    public function showsPublicSkill(): void
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(2);
        $this->subject->showAction($skill);
        /** @var Skill $assignedSkill */
        $assignedSkill = $this->view->_get('variables')['skill'];

        self::assertSame($skill->getUid(), $assignedSkill->getUid());
    }

    /**
     * @test
     */
    public function hidesInternalSkill(): void
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        $this->subject->showAction($skill);

        self::assertArrayNotHasKey('skill', $this->view->_get('variables'));
    }

    /**
     * @test
     */
    public function showsInternalSkillForMember(): void
    {
        $this->simulateLogin();
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        $this->subject->showAction($skill);
        /** @var Skill $assignedSkill */
        $assignedSkill = $this->view->_get('variables')['skill'];

        self::assertSame($skill->getUid(), $assignedSkill->getUid());
    }
}
