<?php

namespace App\Service\Email\Behavior\TemplateContext;



use App\Service\Email\Interface\TemplateContextBehavior;

class TemplateContextVerification implements TemplateContextBehavior
{
    /**
     * @param string $frontendUrl
     * @param string $token
     * @return array
     */
    public function TemplateContext(string $frontendUrl, string $token): array
    {
        return [
            'verify_url' => sprintf('%s/verify-email?token=%s', $frontendUrl, $token)
        ];
    }
}
