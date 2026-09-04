<?php

namespace App\Service\Email\Strategy;

use App\Service\Email\Behavior\LimiterKey\GenericLimiterKey;
use App\Service\Email\Behavior\Subject\GenericSubject;
use App\Service\Email\Behavior\Template\GenericTemplate;
use App\Service\Email\Behavior\Type\GenericType;
use App\Service\Email\Behavior\TemplateContext\TemplateContextResend2FA;
use App\Service\Email\Behavior\TTL\TTLFifteenMinutes;
use App\Enum\EmailType;

class Resend2FAEmail extends Email
{
    public function __construct(string $code)
    {
        $type = EmailType::RESEND_2FA->value;
        $this->limitBehavior = new GenericLimiterKey($type);
        $this->subjectBehavior = new GenericSubject($type);
        $this->templateBehavior = new GenericTemplate($type);
        $this->typeBehavior = new GenericType($type);
        $this->templateContextBehavior = new TemplateContextResend2FA($code);
        $this->ttlBehavior = new TTLFifteenMinutes();
    }
}
