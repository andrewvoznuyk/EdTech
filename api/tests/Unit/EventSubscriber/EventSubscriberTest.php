<?php

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\Event\GoogleUserAuthenticatedEvent;
use App\EventSubscriber\ApiExceptionSubscriber;
use App\EventSubscriber\GoogleAuthSubscriber;
use App\EventSubscriber\UserLocaleSubscriber;
use App\Repository\UserRepository;
use App\Service\Interface\UserRegistrationInterface;
use App\Validator\Exception\AccountDeletedException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class EventSubscriberTest extends TestCase
{
    /**
     * @return void
     */
    public function testApiExceptionSubscriberWithValidationFailedException(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test');

        $violation = new ConstraintViolation('Invalid email format', null, [], null, 'email', 'bad_email');
        $violations = new ConstraintViolationList([$violation]);
        $exception = new ValidationFailedException('Invalid entity', $violations);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('errors', $content);
        $this->assertSame(['Invalid email format'], $content['errors']['email']);
    }

    /**
     * @return void
     */
    public function testApiExceptionSubscriberWithPartialDenormalizationException(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test');

        $notNormalizable = NotNormalizableValueException::createForUnexpectedDataType(
            'Expected int, string given',
            'abc',
            ['int'],
            'quantity'
        );

        $partialException = new PartialDenormalizationException([], [$notNormalizable]);
        // Wrap in a generic exception
        $wrapperException = new \Exception('Denormalization failed', 0, $partialException);

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $wrapperException);
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        $this->assertSame(['This value should be of type int.'], $content['errors']['quantity']);
    }

    /**
     * @return void
     */
    public function testApiExceptionSubscriberWithAccountDeletedException(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test');

        $exception = new AccountDeletedException('restore_token_xyz');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        $this->assertSame('restore_token_xyz', $content['restore_token']);
    }

    /**
     * @return void
     */
    public function testApiExceptionSubscriberWithHttpException(): void
    {
        $subscriber = new ApiExceptionSubscriber();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test');

        $exception = new NotFoundHttpException('Resource not found');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * @return void
     */
    public function testGoogleAuthSubscriberNewUser(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $registrationManager = $this->createMock(UserRegistrationInterface::class);

        $userRepository->method('findOneBy')->with(['email' => 'new@google.com'])->willReturn(null);

        $createdUser = new User();
        $createdUser->setEmail('new@google.com');

        $registrationManager->expects($this->once())
            ->method('registerUser')
            ->with(
                $this->callback(fn (User $user) => $user->getEmail() === 'new@google.com'),
                ['firstName' => 'Taras', 'lastName' => 'Shevchenko'],
                true
            )
            ->willReturn($createdUser);

        $subscriber = new GoogleAuthSubscriber($userRepository, $registrationManager);
        $event = new GoogleUserAuthenticatedEvent('new@google.com', ['given_name' => 'Taras', 'family_name' => 'Shevchenko']);

        $subscriber->onGoogleUserAuthenticated($event);

        $this->assertSame($createdUser, $event->getUser());
    }

    /**
     * @return void
     */
    public function testGoogleAuthSubscriberExistingUser(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $registrationManager = $this->createMock(UserRegistrationInterface::class);

        $existingUser = new User();
        $existingUser->setEmail('existing@google.com');

        $userRepository->method('findOneBy')->with(['email' => 'existing@google.com'])->willReturn($existingUser);
        $registrationManager->expects($this->never())->method('registerUser');

        $subscriber = new GoogleAuthSubscriber($userRepository, $registrationManager);
        $event = new GoogleUserAuthenticatedEvent('existing@google.com', []);

        $subscriber->onGoogleUserAuthenticated($event);

        $this->assertSame($existingUser, $event->getUser());
    }

    /**
     * @return void
     */
    public function testUserLocaleSubscriber(): void
    {
        $security = $this->createMock(Security::class);
        $localeSwitcher = $this->createMock(LocaleSwitcher::class);

        $user = new User();
        $user->setLocale('uk');
        $security->method('getUser')->willReturn($user);

        $localeSwitcher->expects($this->once())->method('setLocale')->with('uk');

        $subscriber = new UserLocaleSubscriber($security, $localeSwitcher);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/any');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertSame('uk', $request->getLocale());
    }
}
