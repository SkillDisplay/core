<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Password extends AbstractEntity
{
    #[Validate(['validator' => 'NotEmpty'])]
    protected string $password = '';

    /**
     * Repetition of Password
     */
    #[Validate(['validator' => 'NotEmpty'])]
    protected string $passwordRepeat = '';

    #[Validate(['validator' => 'NotEmpty'])]
    protected string $oldPassword = '';

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function setPasswordRepeat(string $passwordRepeat): void
    {
        $this->passwordRepeat = $passwordRepeat;
    }

    public function getPasswordRepeat(): string
    {
        return $this->passwordRepeat;
    }

    public function getOldPassword(): string
    {
        return $this->oldPassword;
    }

    public function setOldPassword(string $oldPassword): void
    {
        $this->oldPassword = $oldPassword;
    }
}
