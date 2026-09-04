<?php

namespace App\Service\Email\Interface;

interface TTLBehavior
{
    /**
     * @return int
     */
    public function TimeToLive(): int;
}
