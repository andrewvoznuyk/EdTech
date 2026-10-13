<?php

namespace App\EventSubscriber;

use App\Validator\Exception\AccountDeletedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
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

        $violations = $this->extractViolations($exception);
        if ($violations !== null && count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            $event->setResponse(new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST));
            return;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof PartialDenormalizationException) {
            $errors = [];
            foreach ($previous->getErrors() as $error) {
                $types = $error->getExpectedTypes();
                $expected = !empty($types) ? implode('|', $types) : 'valid';
                $errors[$error->getPath()][] = sprintf('This value should be of type %s.', $expected);
            }

            $event->setResponse(new JsonResponse(['errors' => $errors], Response::HTTP_BAD_REQUEST));
            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $responseData = [
                'message' => $exception->getMessage(),
            ];

            if ($exception instanceof AccountDeletedException) {
                $responseData['restore_token'] = $exception->getRestoreToken();
            }

            $event->setResponse(new JsonResponse($responseData, $exception->getStatusCode()));
        }
    }

    /**
     * @param Throwable $exception
     * @return ConstraintViolationListInterface|null
     */
    private function extractViolations(Throwable $exception): ?ConstraintViolationListInterface
    {
        if (method_exists($exception, 'getConstraintViolationList')) {
            return $exception->getConstraintViolationList();
        }

        if (method_exists($exception, 'getViolations')) {
            return $exception->getViolations();
        }

        $previous = $exception->getPrevious();
        if ($previous !== null) {
            if ($previous instanceof ValidationFailedException) {
                return $previous->getViolations();
            }

            if (method_exists($previous, 'getConstraintViolationList')) {
                return $previous->getConstraintViolationList();
            }

            if (method_exists($previous, 'getViolations')) {
                return $previous->getViolations();
            }
        }

        return null;
    }
}
