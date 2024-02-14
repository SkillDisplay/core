<?php

declare(strict_types=1);

namespace SkillDisplay\Skills\Tests\Functional;

trait SimulateLoginTrait
{
    protected function simulateLogin(): void
    {
        $this->subject?->expects($this->any())->method('getCurrentUser')->will($this->returnValue($this->currentUser));
    }

    protected function simulateLogin2(): void
    {
        if ($this->subject) {
            $user = $this->userRepository->findByUsername('muster2');
            $this->subject->expects($this->any())
                ->method('getCurrentUser')
                ->will($this->returnValue($user));
        }
    }
}
