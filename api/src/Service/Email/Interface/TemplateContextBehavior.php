<?php

namespace App\Service\Email\Interface;

interface TemplateContextBehavior
{
    /**
     * @param string $frontendUrl
     * @param string $token
     * @return array
     */
    public function TemplateContext(string $frontendUrl, string $token): array;
}
