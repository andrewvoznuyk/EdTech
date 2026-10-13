<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\EmailType;
use App\Repository\UserRepository;
use App\Service\Email\EmailSenderService;
use App\Service\Email\Strategy\VerificationEmail;
use App\Service\Interface\UserRegistrationInterface;
use App\State\User\ResendVerificationProcessor;
use App\State\User\UserRegistrationProcessor;
use App\State\User\VerifyEmailProcessor;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class StateProcessorTest extends TestCase
{
    /**
     * @return void
     */
    public function testUserRegistrationProcessor(): void
    {
        $registrationManager = $this->createMock(UserRegistrationInterface::class);
        $emailSenderService = $this->createMock(EmailSenderService::class);

        $processor = new UserRegistrationProcessor($registrationManager, $emailSenderService);

        $user = new User();
        $user->setEmail('register@example.com');

        $registrationManager->expects($this->once())
            ->method('registerUser')
            ->with($user)
            ->willReturn($user);

        $emailSenderService->expects($this->once())
            ->method('setStrategy')
            ->with($this->isInstanceOf(VerificationEmail::class));

        $emailSenderService->expects($this->once())
            ->method('sendEmail')
            ->with($user);

        $result = $processor->process($user, new Post());
        $this->assertSame($user, $result);
    }

    /**
     * @return void
     */
    public function testResendVerificationProcessorSuccess(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $emailSenderService = $this->createMock(EmailSenderService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $processor = new ResendVerificationProcessor(
            $userRepository,
            $emailSenderService,
            $entityManager,
            $translator
        );

        $requestUser = new User();
        $requestUser->setEmail('unverified@example.com');

        $dbUser = new User();
        $dbUser->setEmail('unverified@example.com');
        $dbUser->setIsVerified(false);
        $dbUser->setLastVerificationEmailSentAt(new DateTimeImmutable('-120 seconds'));

        $userRepository->method('findOneBy')->with(['email' => 'unverified@example.com'])->willReturn($dbUser);

        $entityManager->expects($this->once())->method('flush');

        $emailSenderService->expects($this->once())
            ->method('setStrategy')
            ->with($this->isInstanceOf(VerificationEmail::class));

        $emailSenderService->expects($this->once())
            ->method('sendEmail')
            ->with($dbUser);

        $result = $processor->process($requestUser, new Post());
        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testResendVerificationProcessorThrowsOnCooldown(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $emailSenderService = $this->createMock(EmailSenderService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Too many requests');

        $processor = new ResendVerificationProcessor(
            $userRepository,
            $emailSenderService,
            $entityManager,
            $translator
        );

        $requestUser = new User();
        $requestUser->setEmail('unverified@example.com');

        $dbUser = new User();
        $dbUser->setEmail('unverified@example.com');
        $dbUser->setIsVerified(false);
        $dbUser->setLastVerificationEmailSentAt(new DateTimeImmutable('-10 seconds')); // Sent 10s ago (< 60s)

        $userRepository->method('findOneBy')->with(['email' => 'unverified@example.com'])->willReturn($dbUser);

        $this->expectException(TooManyRequestsHttpException::class);
        $processor->process($requestUser, new Post());
    }

    /**
     * @return void
     */
    public function testVerifyEmailProcessorSuccess(): void
    {
        $persistProcessor = $this->createMock(ProcessorInterface::class);
        $userRepository = $this->createMock(UserRepository::class);
        $jwtEncoder = $this->createMock(JWTEncoderInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $translator->method('trans')->willReturn('Success');

        $processor = new VerifyEmailProcessor(
            $persistProcessor,
            $userRepository,
            $jwtEncoder,
            $translator
        );

        $requestUser = new User();
        $requestUser->token = 'valid_verify_token';

        $dbUser = new User();
        $dbUser->setEmail('verify_me@example.com');
        $dbUser->setIsVerified(false);

        $jwtEncoder->method('decode')
            ->with('valid_verify_token')
            ->willReturn(['type' => EmailType::VERIFICATION->value, 'email' => 'verify_me@example.com']);

        $userRepository->method('findOneBy')->with(['email' => 'verify_me@example.com'])->willReturn($dbUser);

        $persistProcessor->expects($this->once())
            ->method('process')
            ->with($dbUser);

        $response = $processor->process($requestUser, new Post());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($dbUser->isVerified());
    }

    /**
     * @return void
     */
    public function testVerifyEmailProcessorInvalidToken(): void
    {
        $persistProcessor = $this->createMock(ProcessorInterface::class);
        $userRepository = $this->createMock(UserRepository::class);
        $jwtEncoder = $this->createMock(JWTEncoderInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Invalid token');

        $processor = new VerifyEmailProcessor(
            $persistProcessor,
            $userRepository,
            $jwtEncoder,
            $translator
        );

        $requestUser = new User();
        $requestUser->token = 'bad_token';

        $jwtEncoder->method('decode')->willThrowException(new JWTDecodeFailureException(JWTDecodeFailureException::INVALID_TOKEN, 'bad'));

        $response = $processor->process($requestUser, new Post());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @return void
     */
    public function testVerifyEmailProcessorAlreadyVerified(): void
    {
        $persistProcessor = $this->createMock(ProcessorInterface::class);
        $userRepository = $this->createMock(UserRepository::class);
        $jwtEncoder = $this->createMock(JWTEncoderInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Already verified');

        $processor = new VerifyEmailProcessor(
            $persistProcessor,
            $userRepository,
            $jwtEncoder,
            $translator
        );

        $requestUser = new User();
        $requestUser->token = 'token';

        $dbUser = new User();
        $dbUser->setEmail('already@example.com');
        $dbUser->setIsVerified(true);

        $jwtEncoder->method('decode')->willReturn(['type' => EmailType::VERIFICATION->value, 'email' => 'already@example.com']);
        $userRepository->method('findOneBy')->willReturn($dbUser);

        $persistProcessor->expects($this->never())->method('process');

        $response = $processor->process($requestUser, new Post());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
