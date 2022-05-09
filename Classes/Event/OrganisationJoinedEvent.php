<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Event;

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\User;

class OrganisationJoinedEvent
{
    /** @var Brand */
    private $organisation;

    /** @var User */
    private $user;

    public function __construct(Brand $organisation, User $user)
    {
        $this->organisation = $organisation;
        $this->user = $user;
    }

    public function getOrganisation(): Brand
    {
        return $this->organisation;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
