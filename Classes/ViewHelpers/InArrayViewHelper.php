<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) Markus Klein, Reelworx GmbH
 **/

namespace SkillDisplay\Skills\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class InArrayViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('needle', 'mixed', '', true);
        $this->registerArgument('haystack', 'array', '', true);
    }

    public function render(): bool
    {
        $needle = (string)$this->arguments['needle'];
        $haystack = (array)$this->arguments['haystack'];
        return in_array($needle, $haystack);
    }
}
