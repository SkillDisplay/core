<?php
declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Unit\Validation;

use PHPUnit\Framework\MockObject\MockObject;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Validation\Validator\CreateUserValidator;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3\CMS\Extbase\Error\Error;

/**
 * Test case.
 *
 * @author Markus Klein <markus.klein@reelworx.at>
 */
class CreateUserValidatorTest extends UnitTestCase
{
    protected $resetSingletonInstances = true;

    /** @var CreateUserValidator|MockObject */
    protected $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->getMockBuilder(CreateUserValidator::class)->disableOriginalConstructor()->onlyMethods(['getErrorMessage'])->getMock();
        $this->subject->expects($this->any())->method('getErrorMessage')->will($this->returnValue(''));
    }

    public function isValidTestsPasswordsCorrectlyDataProvider() : array
    {
        // min 8 chars and a digit
        return [
            'too short' => ['asdf', false],
            'no numbers' => ['Ubkljdfghe', false],
            'valid simple' => ['Ubkljdfghe1', true],
            'valid simple2' => ['1Ubkljdfghe', true],
            'valid complex' => ['Cq2L+XWC;cPE%8U', true],
        ];
    }

    /**
     * @param string $password
     * @param bool $valid
     * @test
     * @dataProvider isValidTestsPasswordsCorrectlyDataProvider
     */
    public function isValidTestsPasswordsCorrectly(string $password, bool $valid)
    {
        $user = new User();
        $user->setPassword($password);
        $user->setPasswordRepeat($password);
        $this->subject->validate($user);
        $pwdValid = true;
        /** @var Error $error */
        foreach ($this->subject->validate($user)->getErrors() as $error) {
            if ($error->getCode() === 1471702623) {
                $pwdValid = false;
                break;
            }
        }
        $this->assertSame($valid, $pwdValid);
    }
}
