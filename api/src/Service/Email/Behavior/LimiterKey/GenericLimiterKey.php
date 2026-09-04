<?php

namespace App\Service\Email\Behavior\LimiterKey;

use App\Service\Email\Interface\LimiterKeyBehavior;

readonly class GenericLimiterKey implements LimiterKeyBehavior
{
    /**
     * @param string $type
     */
    public function __construct(private string $type) {}

    /**
     * @param string $userEmail
     * @return string
     */
    public function LiterKey(string $userEmail): string
    {
        return $userEmail . '_' . $this->type;
    }
}
