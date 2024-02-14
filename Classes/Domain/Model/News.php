<?php

namespace SkillDisplay\Skills\Domain\Model;

class News extends \GeorgRinger\News\Domain\Model\News
{
    protected int $brand = 0;

    public function getBrand(): int
    {
        return $this->brand;
    }

    public function setBrand(int $brand): void
    {
        $this->brand = $brand;
    }
}
