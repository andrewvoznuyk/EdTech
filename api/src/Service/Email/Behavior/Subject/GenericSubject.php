<?php

namespace App\Service\Email\Behavior\Subject;

use App\Service\Email\Interface\SubjectBehavior;

readonly class GenericSubject implements SubjectBehavior
{
    /**
     * @param string $type
     */
    public function __construct(private string $type) {}

    /**
     * @return string
     */
    public function Subject(): string
    {
        return 'subject.' . $this->type;
    }
}
