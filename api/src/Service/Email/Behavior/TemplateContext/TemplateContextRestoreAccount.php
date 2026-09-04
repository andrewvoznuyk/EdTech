<?php

namespace App\Service\Email\Behavior\TemplateContext;

use App\Service\Email\Interface\TemplateContextBehavior;

class TemplateContextRestoreAccount implements TemplateContextBehavior
{
    /**
     * @param string $frontendUrl
     * @param string $token
     * @return array
     */
    public function TemplateContext(string $frontendUrl, string $token): array
    {
        return [
            'action_url' => sprintf('%s/restore-account?token=%s', $frontendUrl, $token)
        ];
    }
}
