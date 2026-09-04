<?php

namespace App\Service\Email\Strategy;

use App\Service\Email\Behavior\LimiterKey\GenericLimiterKey;
use App\Service\Email\Behavior\Subject\GenericSubject;
use App\Service\Email\Behavior\Template\GenericTemplate;
use App\Service\Email\Behavior\Type\GenericType;
use App\Service\Email\Behavior\TemplateContext\TemplateContextPasswordReset;
use App\Service\Email\Behavior\TTL\TTLOneHour;
use App\Enum\EmailType;

class PasswordResetEmail extends Email
{
    public function __construct()
    {
        $type = EmailType::PASSWORD_RESET->value;
        $this->limitBehavior = new GenericLimiterKey($type);
        $this->subjectBehavior = new GenericSubject($type);
        $this->templateBehavior = new GenericTemplate($type);
        $this->typeBehavior = new GenericType($type);
        $this->templateContextBehavior = new TemplateContextPasswordReset();
        $this->ttlBehavior = new TTLOneHour();
    }
}
