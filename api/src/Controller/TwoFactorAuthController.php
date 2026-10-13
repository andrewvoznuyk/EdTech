<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\TwoFactorMethodEnum;
use App\Service\TwoFactor\TwoFactorAuthenticationServiceInterface;
use App\Service\User\UserTwoFactorSetupServiceInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TwoFactorAuthController extends AbstractController
{
    /**
     * @param TwoFactorAuthenticationServiceInterface $authenticationService
     * @param UserTwoFactorSetupServiceInterface $setupService
     * @param NormalizerInterface $normalizer
     * @param TranslatorInterface $translator
     * @param AuthenticationSuccessHandler $successHandler
     */
    public function __construct(
        private readonly TwoFactorAuthenticationServiceInterface $authenticationService,
        private readonly UserTwoFactorSetupServiceInterface $setupService,
        private readonly NormalizerInterface $normalizer,
        private readonly TranslatorInterface $translator,
        private readonly AuthenticationSuccessHandler $successHandler,
    ) {
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/api/2fa/check', name: 'api_2fa_check', methods: ['POST'])]
    public function check(Request $request): Response
    {
        $payload = $request->getPayload();
        $preAuthToken = $payload->getString('pre_auth_token');
        if (!$preAuthToken) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.token_required', [], 'validators'));
        }

        $user = $this->authenticationService->authenticate(
            $preAuthToken,
            $payload->getString('code'),
            $payload->getString('method')
        );

        return $this->successHandler->handleAuthenticationSuccess($user);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/api/2fa/resend', name: 'api_2fa_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $payload = $request->getPayload();
        $preAuthToken = $payload->getString('pre_auth_token');
        if (!$preAuthToken) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.token_required', [], 'validators'));
        }

        $methodString = $payload->getString('method', 'email');
        $this->authenticationService->resend($preAuthToken, $methodString);

        return new JsonResponse(['message' => $this->translator->trans('user.two_factor.code_sent', [], 'validators')], Response::HTTP_OK);
    }

    /**
     * @return Response
     */
    #[IsGranted(User::ROLE_USER)]
    #[Route('/api/users/two-factor/totp/setup', name: 'api_user_totp_setup', methods: ['POST'])]
    public function setupTotp(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $result = $this->setupService->setupTotpTwoFactor($user);

        return new JsonResponse($result, Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws ExceptionInterface
     */
    #[IsGranted(User::ROLE_USER)]
    #[Route('/api/users/two-factor/totp/confirm', name: 'api_user_totp_confirm', methods: ['POST'])]
    public function confirmTotp(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $code = $request->getPayload()->getString('code');
        if (!$code) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.code_required', [], 'validators'));
        }

        $result = $this->setupService->confirmTotpTwoFactor($user, $code);

        $data = $this->normalizer->normalize($user, 'array', ['groups' => [User::GET_ITEM_USER]]);
        $data['backup_codes'] = $result['backup_codes'] ?? [];

        return new JsonResponse($data, Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws ExceptionInterface
     */
    #[IsGranted(User::ROLE_USER)]
    #[Route('/api/users/two-factor/disable', name: 'api_user_two_factor_disable', methods: ['POST'])]
    public function disableTwoFactor(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $payload = $request->getPayload();
        $password = $payload->getString('password');

        $methodEnum = null;
        if ($payload->has('method') && '' !== $payload->getString('method')) {
            $methodString = $payload->getString('method');
            $methodEnum = TwoFactorMethodEnum::tryFrom($methodString);
            if (null === $methodEnum || $methodEnum === TwoFactorMethodEnum::BACKUP_CODE) {
                throw new BadRequestHttpException($this->translator->trans('user.two_factor.invalid_method', [], 'validators'));
            }
        }

        if (null !== $user->getPassword() && !$password) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.password_required', [], 'validators'));
        }

        $this->setupService->disableTwoFactor($user, $password, $methodEnum);

        $data = $this->normalizer->normalize($user, 'array', ['groups' => [User::GET_ITEM_USER]]);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws ExceptionInterface
     */
    #[IsGranted(User::ROLE_USER)]
    #[Route('/api/users/two-factor/set-default', name: 'api_user_two_factor_set_default', methods: ['POST'])]
    public function setDefaultMethod(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $methodString = $request->getPayload()->getString('method');
        $methodEnum = TwoFactorMethodEnum::tryFrom($methodString);
        if (!$methodEnum || $methodEnum === TwoFactorMethodEnum::BACKUP_CODE) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.invalid_method', [], 'validators'));
        }

        $this->setupService->setDefaultTwoFactorMethod($user, $methodEnum);

        $data = $this->normalizer->normalize($user, 'array', ['groups' => [User::GET_ITEM_USER]]);

        return new JsonResponse($data, Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[IsGranted(User::ROLE_USER)]
    #[Route('/api/users/two-factor/generate-backup-codes', name: 'api_user_two_factor_generate_backup_codes', methods: ['POST'])]
    public function generateBackupCodes(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $password = $request->getPayload()->getString('password');
        if (null !== $user->getPassword() && !$password) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.password_required', [], 'validators'));
        }

        $codes = $this->setupService->generateBackupCodes($user, $password);

        return new JsonResponse(['backup_codes' => $codes], Response::HTTP_OK);
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

        return new JsonResponse(['message' => $this->translator->trans('user.two_factor.code_sent', [], 'validators')], Response::HTTP_OK);
    }

    /**
     * @param Request $request
     * @return Response
     * @throws ExceptionInterface
     */
    #[IsGranted(User::ROLE_USER)]
    #[Route('/api/users/confirm-email-two-fa', name: 'api_user_confirm_email_2fa', methods: ['POST'])]
    public function confirmEmailTwoFactor(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $code = $request->getPayload()->getString('code');
        if (!$code) {
            throw new BadRequestHttpException($this->translator->trans('user.two_factor.code_required', [], 'validators'));
        }

        $result = $this->setupService->confirmEmailTwoFactor($user, $code);

        $data = $this->normalizer->normalize($user, 'array', ['groups' => [User::GET_ITEM_USER]]);
        $data['backup_codes'] = $result['backup_codes'] ?? [];

        return new JsonResponse($data, Response::HTTP_OK);
    }
}
