<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) Reelworx GmbH
 **/

namespace SkillDisplay\Skills;

use RuntimeException;

class TermsException extends RuntimeException
{
    public function __construct(private readonly string $url)
    {
        parent::__construct();
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
