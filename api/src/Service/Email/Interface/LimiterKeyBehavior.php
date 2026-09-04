<?php

namespace App\Service\Email\Interface;

interface LimiterKeyBehavior
{
    /**
     * @param string $userEmail
     * @return string
     */
    public function LiterKey(string $userEmail): string;
}
