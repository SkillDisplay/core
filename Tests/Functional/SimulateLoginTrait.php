<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional;

use SkillDisplay\Skills\Domain\Model\User;

trait SimulateLoginTrait
{
    protected function simulateLogin(): void
    {
        $this->simulateLogin2($this->currentUser);
    }

    protected function simulateLogin2(?User $user = null): void
    {
        $user = $user ?: $this->userRepository->findByUsername('muster2');
        $this->subject->expects($this->any())
            ->method('getCurrentUser')
            ->willReturn($user);
    }
}
