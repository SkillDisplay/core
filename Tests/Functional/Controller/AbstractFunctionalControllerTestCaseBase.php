<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\JsonView;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;

abstract class AbstractFunctionalControllerTestCaseBase extends AbstractFunctionalTestCaseBase
{
    protected User $currentUser;
    protected UserRepository $userRepository;

    protected AccessibleObjectInterface|MockObject|JsonView $view;

    protected function setUp(): void
    {
        parent::setUp();

        // disable file processing for tests
        $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_enabled'] = false;

        $this->setUpDatabase();
        $this->userRepository = GeneralUtility::makeInstance(UserRepository::class);
        $this->currentUser = $this->userRepository->findByUsername('muster');
        $this->view = $this->getAccessibleMock(JsonView::class, null);
    }

    protected function setUpDatabase(): void {}

    protected function initController(ActionController|AccessibleObjectInterface $controller): void
    {
        $controller->injectResponseFactory(GeneralUtility::makeInstance(ResponseFactory::class));
        $controller->injectStreamFactory(GeneralUtility::makeInstance(StreamFactory::class));
        $controller->_set('view', $this->view);
    }
}
