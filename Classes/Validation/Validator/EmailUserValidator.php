<?php declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Markus Klein
 *
 ***/

namespace SkillDisplay\Skills\Validation\Validator;

use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Validation\AbstractUserValidator;

class EmailUserValidator extends AbstractUserValidator
{

    /**
     * Check if $value is valid. If it is not valid, needs to add an error
     * to result.
     *
     * @param User $user
     * @return void
     */
    protected function isValid($user)
    {
        if (!$user instanceof User) {
            $this->addError('The given object is not a User object.', 1471702618);
            return;
        }
        $this->validateEmail($user);
    }
}
