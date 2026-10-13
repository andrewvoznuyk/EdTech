<?php

namespace App\Tests\Unit\Validator;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Validator\Constraints\UniqueExceptUnverified;
use App\Validator\GoogleTokenValidator;
use App\Validator\UniqueExceptUnverifiedValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidatorTest extends TestCase
{
    /**
     * @return void
     */
    public function testGoogleTokenValidatorThrowsIfEmptyToken(): void
    {
        $validator = new GoogleTokenValidator('google-client-id');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('user.google_auth.token_required');

        $validator->validateToken(null);
    }

    /**
     * @return void
     */
    public function testGoogleTokenValidatorThrowsIfInvalidFormat(): void
    {
        $validator = new GoogleTokenValidator('google-client-id');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('user.google_auth.invalid_format');

        $validator->validateToken('not-a-valid-google-jwt');
    }

    /**
     * @return void
     */
    public function testUniqueExceptUnverifiedValidatorAllowsUnverifiedUser(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(UserRepository::class);

        $existingUser = new User();
        $existingUser->setEmail('duplicate@example.com');
        $existingUser->setIsVerified(false); // Unverified!

        $entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);
        $userRepository->method('findOneBy')->with(['email' => 'duplicate@example.com'])->willReturn($existingUser);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $validator = new UniqueExceptUnverifiedValidator($entityManager);
        $validator->initialize($context);

        $constraint = new UniqueExceptUnverified(
            entityClass: User::class,
            fields: ['email'],
            message: 'Email already exists'
        );

        $dto = new \stdClass();
        $dto->email = 'duplicate@example.com';

        $validator->validate($dto, $constraint);
    }

    /**
     * @return void
     */
    public function testUniqueExceptUnverifiedValidatorRejectsVerifiedUser(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(UserRepository::class);

        $existingUser = new User();
        $existingUser->setEmail('duplicate@example.com');
        $existingUser->setIsVerified(true); // Verified!

        $entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);
        $userRepository->method('findOneBy')->with(['email' => 'duplicate@example.com'])->willReturn($existingUser);

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->willReturnSelf();
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with('Email already exists')
            ->willReturn($builder);

        $validator = new UniqueExceptUnverifiedValidator($entityManager);
        $validator->initialize($context);

        $constraint = new UniqueExceptUnverified(
            entityClass: User::class,
            fields: ['email'],
            message: 'Email already exists'
        );

        $dto = new \stdClass();
        $dto->email = 'duplicate@example.com';

        $validator->validate($dto, $constraint);
    }

    /**
     * @return void
     */
    public function testUniqueExceptUnverifiedValidatorPassesWhenNoEntityFound(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(UserRepository::class);

        $entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);
        $userRepository->method('findOneBy')->willReturn(null);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $validator = new UniqueExceptUnverifiedValidator($entityManager);
        $validator->initialize($context);

        $constraint = new UniqueExceptUnverified(
            entityClass: User::class,
            fields: ['email'],
            message: 'Email already exists'
        );

        $dto = new \stdClass();
        $dto->email = 'unique@example.com';

        $validator->validate($dto, $constraint);
    }
}
