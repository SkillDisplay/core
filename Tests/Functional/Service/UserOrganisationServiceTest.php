<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional\Service;

use Doctrine\DBAL\DBALException;
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
use TYPO3\TestingFramework\Core\Exception;

class UserOrganisationServiceTest extends AbstractFunctionalTestCaseBase
{
    /** @var SkillPathRepository */
    protected $skillSetRepository;

    /** @var SkillRepository */
    protected $skillRepository;

    /** @var UserRepository */
    protected $userRepository;

    /** @var BrandRepository */
    protected $brandRepository;

    /**
     * @throws DBALException
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->skillSetRepository = $this->objectManager->get(SkillPathRepository::class);
        $this->skillRepository = $this->objectManager->get(SkillRepository::class);
        $this->userRepository = $this->objectManager->get(UserRepository::class);
        $this->brandRepository = $this->objectManager->get(BrandRepository::class);
        $this->importDataSet(__DIR__ . '/../Fixtures/user_access_test.xml');
    }

    /**
     * @test
     */
    public function publicSkillSetVisibleForNonMember()
    {
        /** @var SkillPath $skillSet */
        $skillSet = $this->skillSetRepository->findByUid(2);
        /** @var User $user */
        $user = $this->userRepository->findByUid(2);
        self::assertEquals(true, UserOrganisationsService::isSkillPathVisibleForUser($skillSet, $user));
    }

    /**
     * @test
     */
    public function internalSkillSetVisibleForMember()
    {
        /** @var SkillPath $skillSet */
        $skillSet = $this->skillSetRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(1);
        self::assertEquals(true, UserOrganisationsService::isSkillPathVisibleForUser($skillSet, $user));
    }

    /**
     * @test
     */
    public function internalSkillSetNoVisibleForNotMember()
    {
        /** @var SkillPath $skillSet */
        $skillSet = $this->skillSetRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(2);
        self::assertEquals(false, UserOrganisationsService::isSkillPathVisibleForUser($skillSet, $user));
    }

    /**
     * @test
     */
    public function returnsFalseForNotMember()
    {
        /** @var Brand $brand */
        $brand = $this->brandRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(2);
        self::assertEquals(false, UserOrganisationsService::isUserMemberOfOrganisations([$brand], $user));
    }

    /**
     * @test
     */
    public function returnsTrueForMember()
    {
        /** @var Brand $brand */
        $brand = $this->brandRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(1);
        self::assertEquals(true, UserOrganisationsService::isUserMemberOfOrganisations([$brand], $user));
    }

    /**
     * @test
     */
    public function publicSkillVisibleForNonMember()
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(2);
        /** @var User $user */
        $user = $this->userRepository->findByUid(2);
        self::assertEquals(true, UserOrganisationsService::isSkillVisibleForUser($skill, $user));
    }

    /**
     * @test
     */
    public function publicSkillVisibleForGuest()
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(2);
        self::assertEquals(true, UserOrganisationsService::isSkillVisibleForUser($skill, null));
    }

    /**
     * @test
     */
    public function internalSkillVisibleForMember()
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(1);
        self::assertEquals(true, UserOrganisationsService::isSkillVisibleForUser($skill, $user));
    }

    /**
     * @test
     */
    public function internalSkillNoVisibleForNotMember()
    {
        /** @var Skill $skill */
        $skill = $this->skillRepository->findByUid(1);
        /** @var User $user */
        $user = $this->userRepository->findByUid(2);
        self::assertEquals(false, UserOrganisationsService::isSkillVisibleForUser($skill, $user));
    }
}
