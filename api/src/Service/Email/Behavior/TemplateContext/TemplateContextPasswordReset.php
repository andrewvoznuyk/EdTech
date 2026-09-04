<?php

namespace App\Service\Email\Behavior\TemplateContext;



use App\Service\Email\Interface\TemplateContextBehavior;

class TemplateContextPasswordReset implements TemplateContextBehavior
{
    /**
     * @param string $frontendUrl
     * @param string $token
     * @return array
     */
    public function TemplateContext(string $frontendUrl, string $token): array
    {
        return [
            'reset_url' => sprintf('%s/reset-password?token=%s', $frontendUrl, $token)
        ];
    }
}
