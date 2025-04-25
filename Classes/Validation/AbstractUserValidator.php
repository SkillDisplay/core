<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Validation;

use SkillDisplay\Skills\Domain\Model\User;
use SkillDisplay\Skills\Domain\Repository\UserRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

abstract class AbstractUserValidator extends AbstractValidator
{
    protected function validatePassword(User $user): void
    {
        if (empty($user->getPassword()) || empty($user->getPasswordRepeat())) {
            $this->addError($this->getErrorMessage('passwordRequired'), 1471702621);
        } elseif ($user->getPassword() !== $user->getPasswordRepeat()) {
            $this->addError($this->getErrorMessage('passwordMismatch'), 1471702622);
        } elseif (!preg_match('/\\A(?=\\D*\\d).{8,}/', $user->getPassword())) {
            // min 8 chars and a digit
            $this->addError($this->getErrorMessage('passwordCriteria'), 1471702623);
        }
    }

    protected function validateEmail(User $user): void
    {
        if (empty($user->getEmail())) {
            $this->addError($this->getErrorMessage('emailRequired'), 1471702624);
        } elseif (!GeneralUtility::validEmail($user->getEmail())) {
            $this->addError($this->getErrorMessage('emailInvalid'), 1471702627);
        } else {
            /** @var UserRepository $userRepository */
            $userRepository = GeneralUtility::makeInstance(UserRepository::class);
            if ($userRepository->findByUsername($user->getEmail())) {
                $this->addError($this->getErrorMessage('userDuplicate'), 1471702625);
            }
        }
    }

    protected function getErrorMessage(string $type): ?string
    {
        return LocalizationUtility::translate('user.validation.' . $type, 'Skills');
    }
}
