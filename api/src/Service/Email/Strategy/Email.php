<?php

namespace App\Service\Email\Strategy;

use App\Service\Email\Interface\LimiterKeyBehavior;
use App\Service\Email\Interface\SubjectBehavior;
use App\Service\Email\Interface\TemplateBehavior;
use App\Service\Email\Interface\TemplateContextBehavior;
use App\Service\Email\Interface\TTLBehavior;
use App\Service\Email\Interface\TypeBehavior;

abstract class Email
{
    /**
     * @var LimiterKeyBehavior
     */
    public LimiterKeyBehavior $limitBehavior;
    /**
     * @var SubjectBehavior
     */
    public SubjectBehavior $subjectBehavior;
    /**
     * @var TemplateBehavior
     */
    public TemplateBehavior $templateBehavior;
    /**
     * @var TemplateContextBehavior
     */
    public TemplateContextBehavior $templateContextBehavior;
    /**
     * @var TTLBehavior
     */
    public TTLBehavior $ttlBehavior;
    /**
     * @var TypeBehavior
     */
    public TypeBehavior $typeBehavior;

    /**
     * @param string $userEmail
     * @return string
     */
    public function performLimit(string $userEmail): string
    {
        return $this->limitBehavior->LiterKey($userEmail);
    }

    /**
     * @return string
     */
    public function performSubject(): string
    {
        return $this->subjectBehavior->Subject();
    }

    /**
     * @return string
     */
    public function performTemplate(): string
    {
        return $this->templateBehavior->Template();
    }

    /**
     * @param string $frontendUrl
     * @param string $token
     * @return array
     */
    public function performTemplateContext(string $frontendUrl, string $token): array
    {
        return $this->templateContextBehavior->TemplateContext($frontendUrl, $token);
    }

    /**
     * @return int
     */
    public function performTtl(): int
    {
        return $this->ttlBehavior->TimeToLive();
    }

    /**
     * @return string
     */
    public function performType(): string
    {
        return $this->typeBehavior->Type();
    }

    /**
     * @return LimiterKeyBehavior
     */
    public function getLimit(): LimiterKeyBehavior
    {
        return $this->limitBehavior;
    }

    /**
     * @param LimiterKeyBehavior $limit
     * @return void
     */
    public function setLimit(LimiterKeyBehavior $limit): void
    {
        $this->limitBehavior = $limit;
    }

    /**
     * @return SubjectBehavior
     */
    public function getSubject(): SubjectBehavior
    {
        return $this->subjectBehavior;
    }

    /**
     * @param SubjectBehavior $subject
     * @return void
     */
    public function setSubject(SubjectBehavior $subject): void
    {
        $this->subjectBehavior = $subject;
    }

    /**
     * @return TemplateBehavior
     */
    public function getTemplate(): TemplateBehavior
    {
        return $this->templateBehavior;
    }

    /**
     * @param TemplateBehavior $template
     * @return void
     */
    public function setTemplate(TemplateBehavior $template): void
    {
        $this->templateBehavior = $template;
    }

    /**
     * @return TemplateContextBehavior
     */
    public function getTemplateContext(): TemplateContextBehavior
    {
        return $this->templateContextBehavior;
    }

    /**
     * @param TemplateContextBehavior $templateContext
     * @return void
     */
    public function setTemplateContext(TemplateContextBehavior $templateContext): void
    {
        $this->templateContextBehavior = $templateContext;
    }

    /**
     * @return TTLBehavior
     */
    public function getTtl(): TTLBehavior
    {
        return $this->ttlBehavior;
    }

    /**
     * @param TTLBehavior $ttl
     * @return void
     */
    public function setTtl(TTLBehavior $ttl): void
    {
        $this->ttlBehavior = $ttl;
    }

    /**
     * @return TypeBehavior
     */
    public function getType(): TypeBehavior
    {
        return $this->typeBehavior;
    }

    /**
     * @param TypeBehavior $type
     * @return void
     */
    public function setType(TypeBehavior $type): void
    {
        $this->typeBehavior = $type;
    }
}
