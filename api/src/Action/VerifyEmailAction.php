<?php

namespace App\Action;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\EmailType;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class VerifyEmailAction implements ProcessorInterface
{
    /**
     * @param ProcessorInterface $persistProcessor
     * @param UserRepository $userRepository
     * @param JWTEncoderInterface $jwtEncoder
     * @param TranslatorInterface $translator
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private UserRepository $userRepository,
        private JWTEncoderInterface $jwtEncoder,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @param mixed $data
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return mixed
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof User || empty($data->token)) {
            return new JsonResponse(['message' => $this->translator->trans('response.verification.invalid_data', [], 'email')], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = $this->jwtEncoder->decode($data->token);
        } catch (JWTDecodeFailureException $e) {
            return new JsonResponse(['message' => $this->translator->trans('response.verification.invalid_token', [], 'email')], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['type']) || $payload['type'] !== EmailType::VERIFICATION->value || empty($payload['email'])) {
            return new JsonResponse(['message' => $this->translator->trans('response.verification.invalid_type', [], 'email')], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $payload['email']]);

        if (!$user) {
            return new JsonResponse(['message' => $this->translator->trans('response.verification.user_not_found', [], 'email')], Response::HTTP_BAD_REQUEST);
        }

        if ($user->isVerified()) {
            return new JsonResponse(['message' => $this->translator->trans('response.verification.already_verified', [], 'email')], Response::HTTP_OK);
        }

        $user->setIsVerified(true);
        $this->persistProcessor->process($user, $operation, $uriVariables, $context);

        return new JsonResponse(['message' => $this->translator->trans('response.verification.success', [], 'email')], Response::HTTP_OK);
    }
}
