<?php

// src/Service/matchBuilder.php
namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class matchBuilder
{
    private $userA;
    private $userB;
    private $key;
    private $round;
    private $winPlayer;
    private $losePlayer;
    private $parent1;
    private $parent2;
    private $endedAt;

    public function __construct($userA = null, $userB = null, $key = null, $round = 0, $winPlayer = null, $losePlayer = null, $parent1 = null, $parent2 = null, $endedAt = null)
    {
        $this->userA = $userA;
        $this->userB = $userB;
        $this->key = $key;
        $this->round = $round;
        $this->winPlayer = $winPlayer;
        $this->losePlayer = $losePlayer;
        $this->parent1 = $parent1;
        $this->parent2 = $parent2;
        $this->endedAt = $endedAt;
    }

    public function getUserA()
    {
        return $this->userA;
    }

    public function setUserA($userA): void
    {
        $this->userA = $userA;
    }

    public function getUserB()
    {
        return $this->userB;
    }

    public function setUserB($userB): void
    {
        $this->userB = $userB;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setKey($key): void
    {
        $this->key = $key;
    }

    public function getRound()
    {
        return $this->round;
    }

    public function setRound($round): void
    {
        $this->round = $round;
    }

    public function getWinPlayer()
    {
        return $this->winPlayer;
    }

    public function setWinPlayer($winPlayer): void
    {
        $this->winPlayer = $winPlayer;
    }

    public function getLosePlayer()
    {
        return $this->losePlayer;
    }

    public function setLosePlayer($losePlayer): void
    {
        $this->losePlayer = $losePlayer;
    }

    public function getParent1()
    {
        return $this->parent1;
    }

    public function setParent1($parent1): void
    {
        $this->parent1 = $parent1;
    }

    public function getParent2()
    {
        return $this->parent2;
    }

    public function setParent2($parent2): void
    {
        $this->parent2 = $parent2;
    }

    public function getEndedAt(): ?int
    {
        return $this->endedAt;
    }

    public function setEndedAt(?int $endedAt): self
    {
        $this->endedAt = $endedAt;

        return $this;
    }

}
