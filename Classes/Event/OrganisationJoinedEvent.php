<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Event;

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\User;

readonly class OrganisationJoinedEvent
{
    public function __construct(
        private Brand $organisation,
        private User $user
    ) {}

    public function getOrganisation(): Brand
    {
        return $this->organisation;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
