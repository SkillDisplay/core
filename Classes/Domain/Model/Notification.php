<?php

declare(strict_types=1);

/**
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 **/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Notification extends AbstractEntity
{
    public const string TYPE_VERIFICATION_GRANTED = 'VERIFICATION_GRANTED';
    public const string TYPE_VERIFICATION_REVOKED = 'VERIFICATION_REVOKED';
    public const string TYPE_VERIFICATION_REJECTED = 'VERIFICATION_REJECTED';
    public const string TYPE_VERIFICATION_REQUESTED = 'VERIFICATION_REQUESTED';

    protected int $crdate = 0;
    protected ?User $user = null;
    protected string $type = '';
    protected string $reference = '';
    protected string $message = '';

    public const array ApiJsonViewConfiguration = [
        '_only' => [
            'uid',
            'crdate',
            'type',
            'reference',
            'message',
        ],
    ];

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getCrdate(): int
    {
        return $this->crdate;
    }

    public function setCrdate(int $crdate): void
    {
        $this->crdate = $crdate;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
