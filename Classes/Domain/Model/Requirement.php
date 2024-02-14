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

class Requirement extends AbstractEntity
{
    /**
     * @var ObjectStorage<Set>
     * @Cascade("remove")
     */
    protected ObjectStorage $sets;

    public function __construct()
    {
        $this->sets = new ObjectStorage();
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
     * @return ObjectStorage<Set> sets
     */
    public function getSets(): ObjectStorage
    {
        return $this->sets;
    }

    /**
     * Sets the sets
     *
     * @param ObjectStorage<Set> $sets
     */
    public function setSets(ObjectStorage $sets): void
    {
        $this->sets = $sets;
    }
}
