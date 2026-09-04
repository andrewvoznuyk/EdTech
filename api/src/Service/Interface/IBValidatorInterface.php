<?php

namespace App\Service\Interface;

interface IBValidatorInterface
{
    /**
     * @param object $object
     * @return mixed
     */
    public function validate(object $object);
}