<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFunctionalTestCaseBase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'skills',
        'static_info_tables',
        'pdfviewhelpers',
    ];

    protected array $coreExtensionsToLoad = [
        'extensionmanager',
    ];
}
