<?php declare(strict_types=1);

namespace SkillDisplay\Skills\Domain\Validator;

use SkillDisplay\Skills\Domain\Model\Password;
use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Validation\AbstractUserValidator;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Crypto\PasswordHashing\InvalidPasswordHashException;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PasswordValidator extends AbstractUserValidator
{

    public function isValid($password) {
        if (!$password instanceof Password) {
            $this->addError('The given Object is not a Password.', 98145168);
            return;
        }
        $feUserId = GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user')->get('id');
        /** @var User $user */
        $user = $this->userRepository->findByUid($feUserId);
        if (!$user) {
            $this->addError('No login user found.', 981451686);
            return;
        }

        $saltFactory = GeneralUtility::makeInstance(PasswordHashFactory::class);
        $salt = $saltFactory->get($user->getPassword(), 'FE');
        try {
            if (!$salt->checkPassword($password->getOldPassword(), $user->getPassword())) {
                $this->addError($this->getErrorMessage('passwordOld'), 1471702624);
            }
        } catch (InvalidPasswordHashException $e) {
            $this->addError('Existing password has invalid hash.', 1471702628);
        }
        $user->setPassword($password->getPassword());
        $user->setPasswordRepeat($password->getPasswordRepeat());
        $this->validatePassword($user);
    }
}
