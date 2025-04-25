<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class FrontendUserGroup extends AbstractEntity
{
    protected string $title = '';

    protected string $description = '';

    /**
     * @var ObjectStorage<FrontendUserGroup>
     */
    protected ObjectStorage $subgroup;

    /**
     * Constructs a new Frontend User Group
     *
     * @param string $title
     */
    public function __construct(string $title = '')
    {
        $this->setTitle($title);
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->subgroup = new ObjectStorage();
    }

    /**
     * Sets the title value
     *
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Sets the subgroups. Keep in mind that the property is called "subgroup"
     * although it can hold several subgroups.
     *
     * @param ObjectStorage<FrontendUserGroup> $subgroup An object storage containing the subgroups to add
     */
    public function setSubgroup(ObjectStorage $subgroup): void
    {
        $this->subgroup = $subgroup;
    }

    /**
     * Adds a subgroup to the frontend user
     *
     * @param FrontendUserGroup $subgroup
     */
    public function addSubgroup(FrontendUserGroup $subgroup): void
    {
        $this->subgroup->attach($subgroup);
    }

    /**
     * Removes a subgroup from the frontend user group
     *
     * @param FrontendUserGroup $subgroup
     */
    public function removeSubgroup(FrontendUserGroup $subgroup): void
    {
        $this->subgroup->detach($subgroup);
    }

    /**
     * Returns the subgroups. Keep in mind that the property is called "subgroup"
     * although it can hold several subgroups.
     *
     * @return ObjectStorage<FrontendUserGroup> An object storage containing the subgroups
     */
    public function getSubgroup(): ObjectStorage
    {
        return $this->subgroup;
    }
}
