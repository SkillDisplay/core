<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Unit\Validation;

use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Validation\Validator\EditUserValidator;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class EditUserValidatorTest extends UnitTestCase
{
    protected MockObject|EditUserValidator $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->getMockBuilder(EditUserValidator::class)->disableOriginalConstructor()->onlyMethods(['getErrorMessage'])->getMock();
        $this->subject->expects(self::any())->method('getErrorMessage')->willReturn('');
    }

    /**
     * @test
     */
    public function testsForEmptyFirstName(): void
    {
        $user = new User();
        $user->setFirstName('');
        $user->setLastName('last');

        self::assertFalse($this->validateUser($user, [1471702619]));
    }

    /**
     * @test
     */
    public function testsForEmptyLastName(): void
    {
        $user = new User();
        $user->setFirstName('first');
        $user->setLastName('');

        self::assertFalse($this->validateUser($user, [1471702620]));
    }

    /**
     * @test
     */
    public function validForFirstAndLastName(): void
    {
        $user = new User();
        $user->setFirstName('first');
        $user->setLastName('last');

        self::assertTrue($this->validateUser($user, [1471702620, 1471702619]));
    }

    private function validateUser(User $user, array $codes): bool
    {
        $this->subject->validate($user);

        $errors = $this->subject->validate($user)->getErrors();
        /** @var Error $error */
        foreach ($errors as $error) {
            if (in_array($error->getCode(), $codes)) {
                return false;
            }
        }

        return true;
    }
}
