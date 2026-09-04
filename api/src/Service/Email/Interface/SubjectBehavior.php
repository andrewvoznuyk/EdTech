<?php

namespace App\Service\Email\Interface;

interface SubjectBehavior
{
    /**
     * @return string
     */
    public function Subject(): string;
}
