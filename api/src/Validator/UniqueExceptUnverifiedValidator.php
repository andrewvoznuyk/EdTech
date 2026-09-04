<?php

namespace App\Validator;

use App\Validator\Constraints\UniqueExceptUnverified;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueExceptUnverifiedValidator extends ConstraintValidator
{
    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * @param mixed $value
     * @param Constraint $constraint
     * @return void
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueExceptUnverified) {
            throw new UnexpectedTypeException($constraint, UniqueExceptUnverified::class);
        }

        $fields = (array) $constraint->fields;
        $criteria = [];

        foreach ($fields as $field) {
            $fieldValue = $value->{$field} ?? null;
            if ($constraint->ignoreNull && null === $fieldValue) {
                continue;
            }
            $criteria[$field] = $fieldValue;
        }

        if (empty($criteria)) {
            return;
        }

        $entity = $this->entityManager->getRepository($constraint->entityClass)->findOneBy($criteria);

        if (!$entity) {
            return;
        }

        $isVerified = !method_exists($entity, 'isVerified') || $entity->isVerified();

        if ($isVerified) {
            $this->context->buildViolation($constraint->message)
                ->atPath($constraint->errorPath ?? $fields[0])
                ->addViolation();
        }
    }
}
