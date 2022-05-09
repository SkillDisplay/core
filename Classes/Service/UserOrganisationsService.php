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
     * @return Brand[]
     */
    public static function getOrganisationsOrEmpty(?User $user): array
    {
        if ($user) {
            return $user->getOrganisations()->toArray();
        }

        return [];
    }

    /**
     * @param array|ObjectStorage<\SkillDisplay\Skills\Domain\Model\Brand> $brands
     * @param User $user
     * @return bool
     */
    public static function isUserMemberOfOrganisations($brands, ?User $user): bool
    {
        $organisations = [];
        foreach (UserOrganisationsService::getOrganisationsOrEmpty($user) as $organisation) {
            $organisations[] = $organisation->getUid();
        }

        $inOrganisation = false;

        if ($organisations !== []) {
            foreach ($brands as $brand) {
                if (in_array($brand->getUid(), $organisations)) {
                    $inOrganisation = true;
                    break;
                }
            }
        }

        return $inOrganisation;
    }

    public static function isSkillPathVisibleForUser(SkillPath $path, ?User $user): bool
    {
        return $path->getVisibility() !== SkillPath::VISIBILITY_ORGANISATION
            || UserOrganisationsService::isUserMemberOfOrganisations($path->getBrands(), $user);
    }

    static public function isSkillVisibleForUser(Skill $skill, ?User $user): bool
    {
        return $skill->getVisibility() === Skill::VISIBILITY_PUBLIC
            || UserOrganisationsService::isUserMemberOfOrganisations($skill->getBrands(), $user);
    }
}
