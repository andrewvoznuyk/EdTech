<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Entity\UserTwoFactorMethod;
use App\Enum\TwoFactorMethodEnum;
use App\EventListener\AuthenticationFailureListener;
use App\EventListener\AuthenticationSuccessListener;
use App\EventListener\JWTCreatedListener;
use App\EventListener\JWTDecodedListener;
use App\EventListener\UserRemoveListener;
use App\EntityListener\UserAwareListener;
use App\Repository\RefreshTokenRepository;
use App\Service\Interface\UserAwareInterface;
use App\Service\TwoFactor\Token\PreAuthTokenServiceInterface;
use App\Service\TwoFactor\TwoFactorVerifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

class EventListenerTest extends TestCase
{
    /**
     * @return void
     */
    public function testAuthenticationSuccessListenerIntercepts2fa(): void
    {
        $requestStack = new RequestStack();
        $request = Request::create('/api/login-check', 'POST');
        $requestStack->push($request);

        $twoFactorVerifier = $this->createMock(TwoFactorVerifierInterface::class);
        $preAuthTokenService = $this->createMock(PreAuthTokenServiceInterface::class);

        $listener = new AuthenticationSuccessListener(
            $requestStack,
            $twoFactorVerifier,
            $preAuthTokenService
        );

        $user = new User();
        $user->setEmail('user2fa@example.com');
        $method = new UserTwoFactorMethod();
        $method->setMethod(TwoFactorMethodEnum::EMAIL);
        $method->setIsActive(true);
        $method->setIsDefault(true);
        $user->addTwoFactorMethod($method);
        $user->setBackupCodes(['code1']);

        $preAuthTokenService->expects($this->once())
            ->method('createToken')
            ->with($user)
            ->willReturn('challenge.jwt.token');

        $twoFactorVerifier->expects($this->once())
            ->method('prepare')
            ->with($user, TwoFactorMethodEnum::EMAIL);

        $response = new Response();
        $event = new AuthenticationSuccessEvent([], $user, $response);

        $listener->onAuthenticationSuccess($event);

        $data = $event->getData();
        $this->assertTrue($data['require_2fa']);
        $this->assertSame('challenge.jwt.token', $data['pre_auth_token']);
        $this->assertSame('email', $data['method']);
        $this->assertContains('backup_code', $data['available_methods']);
        $this->assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        $this->assertTrue($event->isPropagationStopped());
    }

    /**
     * @return void
     */
    public function testAuthenticationSuccessListenerBypassesWhen2faDisabled(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/login-check', 'POST'));

        $twoFactorVerifier = $this->createMock(TwoFactorVerifierInterface::class);
        $preAuthTokenService = $this->createMock(PreAuthTokenServiceInterface::class);

        $listener = new AuthenticationSuccessListener($requestStack, $twoFactorVerifier, $preAuthTokenService);

        $user = new User();
        $response = new Response();
        $event = new AuthenticationSuccessEvent(['token' => 'jwt'], $user, $response);

        $listener->onAuthenticationSuccess($event);

        $this->assertSame(['token' => 'jwt'], $event->getData());
        $this->assertFalse($event->isPropagationStopped());
    }

    /**
     * @return void
     */
    public function testAuthenticationFailureListener(): void
    {
        $listener = new AuthenticationFailureListener();

        $response = new Response(json_encode(['message' => 'Bad credentials']));
        $exception = new class extends AuthenticationException {
            public function getMessageKey(): string
            {
                return 'error.invalid_credentials';
            }
        };

        $event = new AuthenticationFailureEvent($exception, $response);
        $listener->onAuthenticationFailure($event);

        $responseData = json_decode((string) $response->getContent(), true);
        $this->assertSame('error.invalid_credentials', $responseData['errorCode']);
    }

    /**
     * @return void
     */
    public function testJWTCreatedListenerEnrichesPayload(): void
    {
        $listener = new JWTCreatedListener();

        $user = new User();
        $user->setRoles([User::ROLE_ADMIN]);

        $event = new JWTCreatedEvent(['username' => 'test'], $user, []);
        $listener($event);

        $data = $event->getData();
        $this->assertArrayHasKey('id', $data);
        $this->assertContains(User::ROLE_ADMIN, $data['roles']);
    }

    /**
     * @return void
     */
    public function testJWTDecodedListenerInvalidates2faChallenge(): void
    {
        $listener = new JWTDecodedListener();

        $event = new JWTDecodedEvent(['purpose' => '2fa_challenge']);
        $listener($event);
        $this->assertFalse($event->isValid());

        $normalEvent = new JWTDecodedEvent(['username' => 'normal']);
        $listener($normalEvent);
        $this->assertTrue($normalEvent->isValid());
    }

    /**
     * @return void
     */
    public function testUserRemoveListenerDeletesRefreshTokens(): void
    {
        $listener = new UserRemoveListener();

        $user = new User();
        $user->setEmail('remove@example.com');

        $refreshToken = new RefreshToken();
        $tokenRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $tokenRepository->method('findBy')->with(['username' => 'remove@example.com'])->willReturn([$refreshToken]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(RefreshToken::class)->willReturn($tokenRepository);
        $entityManager->expects($this->once())->method('remove')->with($refreshToken);

        $args = new PreRemoveEventArgs($user, $entityManager);
        $listener->preRemove($user, $args);
    }

    /**
     * @return void
     */
    public function testUserAwareListenerSetsCurrentUser(): void
    {
        $security = $this->createMock(Security::class);
        $listener = new UserAwareListener($security);

        $currentUser = new User();
        $security->method('getUser')->willReturn($currentUser);

        $entity = new class implements UserAwareInterface {
            private ?User $user = null;
            public function getUser(): ?User { return $this->user; }
            public function setUser(?User $user): static { $this->user = $user; return $this; }
        };

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $args = new PrePersistEventArgs($entity, $entityManager);

        $listener->prePersist($args);

        $this->assertSame($currentUser, $entity->getUser());
    }

    /**
     * @return void
     */
    public function testUserAwareListenerThrowsWhenNotAuthenticated(): void
    {
        $security = $this->createMock(Security::class);
        $listener = new UserAwareListener($security);

        $security->method('getUser')->willReturn(null);

        $entity = new class implements UserAwareInterface {
            private ?User $user = null;
            public function getUser(): ?User { return $this->user; }
            public function setUser(?User $user): static { $this->user = $user; return $this; }
        };

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $args = new PrePersistEventArgs($entity, $entityManager);

        $this->expectException(AccessDeniedException::class);
        $listener->prePersist($args);
    }
}
