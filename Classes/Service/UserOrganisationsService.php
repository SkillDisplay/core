<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Service;

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class UserOrganisationsService
{
    /**
     * @param User|null $user
     * @return int[]
     */
    public static function getOrganisationsOrEmpty(?User $user): array
    {
        if (!$user) {
            return [];
        }
        return array_map(fn(Brand $brand) => $brand->getUid(), $user->getOrganisations()->toArray());
    }

    /**
     * @param array|ObjectStorage<Brand> $brands
     * @param User|null $user
     * @return bool
     */
    public static function isUserMemberOfOrganisations(ObjectStorage|array $brands, ?User $user): bool
    {
        $organisations = UserOrganisationsService::getOrganisationsOrEmpty($user);
        if ($organisations !== []) {
            foreach ($brands as $brand) {
                if (in_array($brand->getUid(), $organisations)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function isSkillPathVisibleForUser(SkillPath $path, ?User $user): bool
    {
        return $path->getVisibility() !== SkillPath::VISIBILITY_ORGANISATION
            || UserOrganisationsService::isUserMemberOfOrganisations($path->getBrands(), $user);
    }

    public static function isSkillVisibleForUser(Skill $skill, ?User $user): bool
    {
        return $skill->getVisibility() === Skill::VISIBILITY_PUBLIC
            || UserOrganisationsService::isUserMemberOfOrganisations($skill->getBrands(), $user);
    }
}
