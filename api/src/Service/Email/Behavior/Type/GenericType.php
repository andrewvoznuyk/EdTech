<?php

namespace App\Service\Email\Behavior\Type;

use App\Service\Email\Interface\TypeBehavior;

readonly class GenericType implements TypeBehavior
{
    /**
     * @param string $type
     */
    public function __construct(private string $type) {}

    /**
     * @return string
     */
    public function Type(): string
    {
        return $this->type;
    }
}
