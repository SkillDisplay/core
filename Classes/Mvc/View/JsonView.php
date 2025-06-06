<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Mvc\View;

use TYPO3\CMS\Extbase\Mvc\View\JsonView as ExtbaseJsonView;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class JsonView extends ExtbaseJsonView
{
    /**
     * Transforming ObjectStorages to Arrays for the JSON view
     *
     * @param array $configuration
     * @param false $firstLevel
     * @return array
     */
    protected function transformValue(mixed $value, array $configuration, $firstLevel = false)
    {
        if ($value instanceof ObjectStorage) {
            $value = $value->toArray();
        }
        $configuration['_exclude'][] = 'exportJson';
        return parent::transformValue($value, $configuration, $firstLevel);
    }
}
