<?php

namespace App\Service\Email\Behavior\Template;

use App\Service\Email\Interface\TemplateBehavior;

readonly class GenericTemplate implements TemplateBehavior
{
    /**
     * @param string $type
     */
    public function __construct(private string $type) {}

    /**
     * @return string
     */
    public function Template(): string
    {
        return 'emails/' . $this->type . '.html.twig';
    }
}
