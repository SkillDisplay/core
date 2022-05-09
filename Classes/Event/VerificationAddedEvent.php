<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Event;

use SkillDisplay\Skills\Domain\Model\Certification;

class VerificationAddedEvent
{
    /** @var Certification[] */
    private $verifications;

    /**
     * @param Certification[] $verifications
     */
    public function __construct(array $verifications)
    {
        $this->verifications = $verifications;
    }

    public function getVerifications(): array
    {
        return $this->verifications;
    }
}
