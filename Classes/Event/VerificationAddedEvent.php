<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Event;

use SkillDisplay\Skills\Domain\Model\Certification;

readonly class VerificationAddedEvent
{
    public function __construct(private array $verifications) {}

    /**
     * @return Certification[]
     */
    public function getVerifications(): array
    {
        return $this->verifications;
    }
}
