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

class Requirement extends AbstractEntity
{
    /**
     * sets
     *
     * @var \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Set>
     * @TYPO3\CMS\Extbase\Annotation\ORM\Cascade("remove")
     */
    protected $sets = null;

    public function __construct()
    {
        $this->sets = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
    }

    public function addSet(Set $set): void
    {
        $this->sets->attach($set);
    }

    public function removeSet(Set $setToRemove): void
    {
        $this->sets->detach($setToRemove);
    }

    /**
     * Returns the sets
     *
     * @return \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Set> sets
     */
    public function getSets()
    {
        return $this->sets;
    }

    /**
     * Sets the sets
     *
     * @param \TYPO3\CMS\Extbase\Persistence\ObjectStorage<\SkillDisplay\Skills\Domain\Model\Set> $sets
     */
    public function setSets(\TYPO3\CMS\Extbase\Persistence\ObjectStorage $sets): void
    {
        $this->sets = $sets;
    }
}
