<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Event;

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\User;

class OrganisationJoinedEvent
{
    public function __construct(
        private readonly Brand $organisation,
        private readonly User $user
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
