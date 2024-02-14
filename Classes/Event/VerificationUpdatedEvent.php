<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Event;

use SkillDisplay\Skills\Domain\Model\Certification;

class VerificationUpdatedEvent
{
    public function __construct(private readonly array $verifications) {}

    /**
     * @return Certification[]
     */
    public function getVerifications(): array
    {
        return $this->verifications;
    }
}
