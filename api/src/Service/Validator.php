<?php

namespace App\Service;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Service\Interface\IBValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class Validator implements IBValidatorInterface
{
    /**
     * @param ValidatorInterface $validator
     */
    public function __construct(
        private ValidatorInterface $validator
    ) {}

    /**
     * @param object $object
     * @return void
     */
    public function validate(object $object): void
    {
        $errors = $this->validator->validate($object);

        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }
}
