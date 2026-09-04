<?php

namespace App\Validator\Constraints;

use App\Validator\UniqueExceptUnverifiedValidator;
use Attribute;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[Attribute(Attribute::TARGET_CLASS)]
class UniqueExceptUnverified extends UniqueEntity
{
    /**
     * @var string
     */
    public string $message = 'validator.unique_except_unverified';

    /**
     * @return string
     */
    public function validatedBy(): string
    {
        return UniqueExceptUnverifiedValidator::class;
    }
}
