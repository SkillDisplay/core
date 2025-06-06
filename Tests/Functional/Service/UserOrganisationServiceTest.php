<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Service;

use SkillDisplay\Skills\Domain\Model\Brand;
use SkillDisplay\Skills\Domain\Model\Skill;
use SkillDisplay\Skills\Domain\Model\SkillPath;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\BrandRepository;
use SkillDisplay\Skills\Domain\Repository\SkillPathRepository;
use SkillDisplay\Skills\Domain\Repository\SkillRepository;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Service\UserOrganisationsService;
use SkillDisplay\Skills\Tests\Functional\AbstractFunctionalTestCaseBase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UserOrganisationServiceTest extends AbstractFunctionalTestCaseBase
{
    protected SkillPathRepository $skillSetRepository;
    protected SkillRepository $skillRepository;
    protected UserRepository $userRepository;
    protected BrandRepository $brandRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->skillSetRepository = GeneralUtility::makeInstance(SkillPathRepository::class);
        $this->skillRepository = GeneralUtility::makeInstance(SkillRepository::class);
        $this->userRepository = GeneralUtility::makeInstance(UserRepository::class);
        $this->brandRepository = GeneralUtility::makeInstance(BrandRepository::class);
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/user_access_test.csv');
    }

    /**
     * @test
     */
    public function publicSkillSetVisibleForNonMember(): void
    {
        /** @var SkillPath $skillSet */
        $skillSet = $this->skillSetRepository->findByUid(2);
        /** @var User $user */
        $user = $this->userRepository->findByUid(2);
        self::assertTrue(UserOrganisationsService::isSkillPathVisibleForUser($skillSet, $user));
    }

    /**
     * @test
     */
    public function internalSkillSetVisibleForMember(): void
    {
        /** @var SkillPath $skillSet */
        $skillSet = $this->skillSetRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(1);
        self::assertTrue(UserOrganisationsService::isSkillPathVisibleForUser($skillSet, $user));
    }

    /**
     * @test
     */
    public function internalSkillSetNoVisibleForNotMember(): void
    {
        /** @var SkillPath $skillSet */
        $skillSet = $this->skillSetRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(2);
        self::assertFalse(UserOrganisationsService::isSkillPathVisibleForUser($skillSet, $user));
    }

    /**
     * @test
     */
    public function returnsFalseForNotMember(): void
    {
        /** @var Brand $brand */
        $brand = $this->brandRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(2);
        self::assertFalse(UserOrganisationsService::isUserMemberOfOrganisations([$brand], $user));
    }

    /**
     * @test
     */
    public function returnsTrueForMember(): void
    {
        /** @var Brand $brand */
        $brand = $this->brandRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(1);
        self::assertTrue(UserOrganisationsService::isUserMemberOfOrganisations([$brand], $user));
    }

    /**
     * @test
     */
    public function publicSkillVisibleForNonMember(): void
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(2);
        /** @var User $user */
        $user = $this->userRepository->findByUid(2);
        self::assertTrue(UserOrganisationsService::isSkillVisibleForUser($skill, $user));
    }

    /**
     * @test
     */
    public function publicSkillVisibleForGuest(): void
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(2);
        self::assertTrue(UserOrganisationsService::isSkillVisibleForUser($skill, null));
    }

    /**
     * @test
     */
    public function internalSkillVisibleForMember(): void
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(1);
        self::assertTrue(UserOrganisationsService::isSkillVisibleForUser($skill, $user));
    }

    /**
     * @test
     */
    public function internalSkillNoVisibleForNotMember(): void
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(2);
        self::assertFalse(UserOrganisationsService::isSkillVisibleForUser($skill, $user));
    }
}
