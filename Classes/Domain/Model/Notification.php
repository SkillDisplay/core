<?php

declare(strict_types=1);
/***
 *
 * This file is part of the "Skill Display" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2021 Reelworx GmbH
 *
 ***/

namespace SkillDisplay\Skills\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Notification extends AbstractEntity
{
    public const TYPE_VERIFICATION_GRANTED = 'VERIFICATION_GRANTED';
    public const TYPE_VERIFICATION_REVOKED = 'VERIFICATION_REVOKED';
    public const TYPE_VERIFICATION_REJECTED = 'VERIFICATION_REJECTED';
    public const TYPE_VERIFICATION_REQUESTED = 'VERIFICATION_REQUESTED';

    protected int $crdate = 0;

    protected ?User $user = null;

    protected string $type = '';

    protected string $reference = '';

    protected string $message = '';

    public const ApiJsonViewConfiguration = [
        '_only' => [
            'uid',
            'crdate',
            'type',
            'reference',
            'message',
        ]
    ];


    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return int
     */
    public function getCrdate(): int
    {
        return $this->crdate;
    }

    /**
     * @param int $crdate
     */
    public function setCrdate(int $crdate): void
    {
        $this->crdate = $crdate;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @param string $reference
     */
    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
