<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *           Georg Ringer
 **/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\Annotation\ORM\Cascade;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Set extends AbstractEntity
{
    /**
     * skills
     *
     * @var ObjectStorage<SetSkill>
     */
    #[Cascade(['value' => 'remove'])]
    protected ObjectStorage $skills;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->skills = new ObjectStorage();
    }

    public function addSkill(SetSkill $skill): void
    {
        $this->skills->attach($skill);
    }

    public function removeSkill(SetSkill $skillToRemove): void
    {
        $this->skills->detach($skillToRemove);
    }

    /**
     * @return ObjectStorage<SetSkill>
     */
    public function getSkills(): ObjectStorage
    {
        return $this->skills;
    }

    public function setSkills(ObjectStorage $skills): void
    {
        $this->skills = $skills;
    }
}
