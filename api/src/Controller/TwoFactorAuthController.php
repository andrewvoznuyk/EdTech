<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\EmailType;
use App\Enum\TwoFactorMethodEnum;
use App\Service\TwoFactor\TwoFactorManager;
use App\Service\User\UserTwoFactorSetupService;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TwoFactorAuthController extends AbstractController
{
    /**
     * @param UserTwoFactorSetupService $setupService
     * @param EntityManagerInterface $entityManager
     * @param RateLimiterFactory $twoFactorAuthLimiter
     * @param TwoFactorManager $twoFactorManager
     * @param NormalizerInterface $normalizer
     * @param RateLimiterFactory $emailLimiter
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private readonly UserTwoFactorSetupService $setupService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RateLimiterFactory $twoFactorAuthLimiter,
        private readonly TwoFactorManager $twoFactorManager,
        private readonly NormalizerInterface $normalizer,
        private readonly RateLimiterFactory $emailLimiter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param Request $request
     * @param AuthenticationSuccessHandler $successHandler
     * @return Response
     * @throws JsonException
     */
    #[Route('/api/2fa/check', name: 'api_2fa_check', methods: ['POST'])]
    public function check(Request $request, AuthenticationSuccessHandler $successHandler,): Response {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $email = $data['email'] ?? null;
        $code = $data['code'] ?? null;
        $methodString = $data['method'] ?? 'email';

        if (!$email || !$code) {
            return new JsonResponse(['message' => $this->translator->trans('user.two_factor.email_code_required', [], 'validators')], Response::HTTP_BAD_REQUEST);
        }

        $methodEnum = TwoFactorMethodEnum::tryFrom($methodString);
        if (!$methodEnum) {
            return new JsonResponse(['message' => $this->translator->trans('user.two_factor.invalid_method', [], 'validators')], Response::HTTP_BAD_REQUEST);
        }

        $limiter = $this->twoFactorAuthLimiter->create($email);
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return new JsonResponse(['message' => $this->translator->trans('user.two_factor.user_not_found', [], 'validators')], Response::HTTP_NOT_FOUND);
        }

        if (!in_array($methodString, $user->getActiveTwoFactorMethods(), true)) {
            return new JsonResponse(['message' => $this->translator->trans('user.two_factor.method_not_enabled', [], 'validators')], Response::HTTP_BAD_REQUEST);
        }

        $this->twoFactorManager->setProviderByEnum($methodEnum);
        if (!$this->twoFactorManager->validate($user, $code)) {
            return new JsonResponse(['message' => $this->translator->trans('user.two_factor.invalid_code', [], 'validators')], Response::HTTP_BAD_REQUEST);
        }

        $this->twoFactorAuthLimiter->create($email)->reset();
        $this->emailLimiter->create($email . '_' . EmailType::RESEND_2FA->value)->reset();

        return $successHandler->handleAuthenticationSuccess($user);
    }

    /**
     * @return Response
     */
    #[IsGranted(User::ROLE_USER)]
    #[Route('/api/users/enable-email-two-fa', name: 'api_user_enable_email_2fa', methods: ['POST'])]
    public function enableEmailTwoFactor(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->setupService->enableEmailTwoFactor($user);

        $this->twoFactorManager->setProviderByEnum(TwoFactorMethodEnum::EMAIL);
        $this->twoFactorManager->prepare($user);

        return new JsonResponse(['message' => $this->translator->trans('user.two_factor.code_sent', [], 'validators')], Response::HTTP_OK);
    }

    /**
     * @throws ExceptionInterface
     * @throws JsonException
     */
    #[IsGranted(User::ROLE_USER)]
    #[Route('/api/users/confirm-email-two-fa', name: 'api_user_confirm_email_2fa', methods: ['POST'])]
    public function confirmEmailTwoFactor(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $request->getPayload();
        $code = $data->getString('code');

        if (!$code) {
            return new JsonResponse(['message' => $this->translator->trans('user.two_factor.code_required', [], 'validators')], Response::HTTP_BAD_REQUEST);
        }

        $this->twoFactorManager->setProviderByEnum(TwoFactorMethodEnum::EMAIL);
        if (!$this->twoFactorManager->validate($user, $code)) {
            return new JsonResponse(['message' => $this->translator->trans('user.two_factor.invalid_code', [], 'validators')], Response::HTTP_BAD_REQUEST);
        }

        foreach ($user->getTwoFactorMethods() as $method) {
            if ($method->getMethod() === TwoFactorMethodEnum::EMAIL) {
                $method->setIsActive(true);
                break;
            }
        }

        $this->entityManager->flush();

        $data = $this->normalizer->normalize($user, 'array', ['groups' => [User::GET_ITEM_USER]]);

        return new JsonResponse($data, Response::HTTP_OK);
    }
}
