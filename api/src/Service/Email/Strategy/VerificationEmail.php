<?php

namespace App\Service\Email\Strategy;

use App\Service\Email\Behavior\LimiterKey\GenericLimiterKey;
use App\Service\Email\Behavior\Subject\GenericSubject;
use App\Service\Email\Behavior\Template\GenericTemplate;
use App\Service\Email\Behavior\Type\GenericType;
use App\Service\Email\Behavior\TemplateContext\TemplateContextVerification;
use App\Service\Email\Behavior\TTL\TTLFifteenMinutes;
use App\Enum\EmailType;

class VerificationEmail extends Email
{
    public function __construct()
    {
        $type = EmailType::VERIFICATION->value;
        $this->limitBehavior = new GenericLimiterKey($type);
        $this->subjectBehavior = new GenericSubject($type);
        $this->templateBehavior = new GenericTemplate($type);
        $this->typeBehavior = new GenericType($type);
        $this->templateContextBehavior = new TemplateContextVerification();
        $this->ttlBehavior = new TTLFifteenMinutes();
    }
}
