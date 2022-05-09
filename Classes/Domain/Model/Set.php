<?php declare(strict_types=1);

/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *           Georg Ringer
 *
 ***/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Set extends AbstractEntity
{
    /**
     * skills
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\SetSkill>
     * @TYPO3\CMS\Extbase\Annotation\ORM\Cascade("remove")
     */
    protected $skills = null;

    public function __construct()
    {
        $this->skills = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
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
     * Returns the skills
     *
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\SetSkill>
     */
    public function getSkills()
    {
        return $this->skills;
    }

    public function setSkills(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $skills): void
    {
        $this->skills = $skills;
    }
}
