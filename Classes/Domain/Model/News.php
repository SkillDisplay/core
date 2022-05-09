<?php

namespace SkillDisplay\Skills\Domain\Model;

class News extends \GeorgRinger\News\Domain\Model\News {

    /** @var int */
    protected $brand = 0;

    public function getBrand(): int
    {
        return $this->brand;
    }

    public function setBrand(int $brand): void
    {
        $this->brand = $brand;
    }
}
