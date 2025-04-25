<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Routing;

use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;

class UidMapper implements StaticMappableAspectInterface
{
    public function generate(string $value): ?string
    {
        return $value;
    }

    public function resolve(string $value): ?string
    {
        return $value;
    }
}
