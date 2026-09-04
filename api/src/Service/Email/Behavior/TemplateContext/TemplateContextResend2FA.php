<?php

namespace App\Service\Email\Behavior\TemplateContext;

use App\Service\Email\Interface\TemplateContextBehavior;

readonly class TemplateContextResend2FA implements TemplateContextBehavior
{
    public function __construct(
        private string $code
    ) {}

    /**
     * @param string $frontendUrl
     * @param string $token
     * @return array
     */
    public function TemplateContext(string $frontendUrl, string $token): array
    {
        return [
            'auth_code' => $this->code
        ];
    }
}
