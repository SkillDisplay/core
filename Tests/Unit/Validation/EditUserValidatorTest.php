<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Unit\Validation;

use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use SkillDisplay\Skills\Validation\Validator\EditUserValidator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3\CMS\Extbase\Error\Error;

class EditUserValidatorTest extends UnitTestCase
{
    /** @var EditUserValidator|MockObject */
    protected $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->getMockBuilder(EditUserValidator::class)->disableOriginalConstructor()->onlyMethods(['getErrorMessage'])->getMock();
        $this->subject->expects($this->any())->method('getErrorMessage')->will($this->returnValue(''));
    }

    /**
     * @test
     */
    public function testsForEmptyFirstName()
    {
        $user = new User();
        $user->setFirstName('');
        $user->setLastName('last');

        $this->assertEquals(false, $this->validateUser($user, [1471702619]));
    }

    /**
     * @test
     */
    public function testsForEmptyLastName()
    {
        $user = new User();
        $user->setFirstName('first');
        $user->setLastName('');

        $this->assertEquals(false, $this->validateUser($user, [1471702620]));
    }

    /**
     * @test
     */
    public function validForFirstAndLastName()
    {
        $user = new User();
        $user->setFirstName('first');
        $user->setLastName('last');

        $this->assertEquals(true, $this->validateUser($user, [1471702620, 1471702619]));
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
