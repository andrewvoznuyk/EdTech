<?php

namespace App\Service\Email\Behavior\TTL;

use App\Service\Email\Interface\TTLBehavior;

class TTLOneHour implements TTLBehavior
{
    /**
     * @return int
     */
    public function TimeToLive(): int
    {
        return 3600;
    }
}
