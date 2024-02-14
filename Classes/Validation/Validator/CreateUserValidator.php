<?php

declare(strict_types=1);
/**
* This file is part of the "Skill Display" Extension for TYPO3 CMS.
*
* For the full copyright and license information, please read the
* LICENSE.txt file that was distributed with this source code.
*
*  (c) 2016 Markus Klein
**/

namespace SkillDisplay\Skills\Validation\Validator;

use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Validation\AbstractUserValidator;

class CreateUserValidator extends AbstractUserValidator
{
    /**
     * Check if $value is valid. If it is not valid, needs to add an error
     * to result.
     *
     * @param User $user
     */
    protected function isValid($user)
    {
        if (!$user instanceof User) {
            $this->addError('The given object is not a User object.', 1471702618);
            return;
        }
        if (empty($user->getFirstName())) {
            $this->addError($this->getErrorMessage('firstNameRequired'), 1471702619);
        }
        if (empty($user->getLastName())) {
            $this->addError($this->getErrorMessage('lastNameRequired'), 1471702620);
        }

        $this->validatePassword($user);
        $this->validateEmail($user);

        if (!$user->isTerms()) {
            $this->addError($this->getErrorMessage('terms'), 1471702626);
        }
    }
}
