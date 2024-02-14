<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Hook;

use TYPO3\CMS\Core\DataHandling\DataHandler;

class ImportDataHandler extends DataHandler
{
    protected function processClearCacheQueue()
    {
        // do nothing
    }
}
