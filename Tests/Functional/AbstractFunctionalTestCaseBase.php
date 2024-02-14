<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFunctionalTestCaseBase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/skills',
        'typo3conf/ext/static_info_tables',
        'typo3conf/ext/pdfviewhelpers',
    ];
}
