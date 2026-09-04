<?php

namespace App\EventSubscriber;

use App\Validator\Exception\AccountDeletedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @param TranslatorInterface $translator
     */
    public function __construct(
        private TranslatorInterface $translator
    ) {}

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    /**
     * @param ExceptionEvent $event
     * @return void
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface) {
            $messageKey = $exception->getMessage();

            $translatedMessage = $this->translator->trans($messageKey, [], 'validators');
            $finalMessage = ($translatedMessage === $messageKey) ? $exception->getMessage() : $translatedMessage;

            $responseData = ['message' => $finalMessage];

            if ($exception instanceof AccountDeletedException) {
                $responseData['restore_token'] = $exception->getRestoreToken();
            }

            $response = new JsonResponse($responseData, $exception->getStatusCode());
            $event->setResponse($response);
        }
    }
}
