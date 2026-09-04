<?php

namespace App\Service\Email\Interface;

interface TemplateBehavior
{
    /**
     * @return string
     */
    public function Template(): string;
}
