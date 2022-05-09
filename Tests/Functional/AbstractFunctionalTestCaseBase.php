<?php

namespace SkillDisplay\Skills\Tests\Functional;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFunctionalTestCaseBase extends FunctionalTestCase
{
    protected $testExtensionsToLoad = ['typo3conf/ext/skills', 'typo3conf/ext/static_info_tables', 'typo3conf/ext/pdfviewhelpers'];

    /** @var ObjectManagerInterface */
    protected $objectManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
    }
}
