<?php

namespace App\Service\EntityService\File;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\File;
use App\Enum\FileStatus;
use App\Enum\FileType;
use App\Enum\MimeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @implements FileServiceInterface
 */
readonly class FileService implements FileServiceInterface
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @param UploadedFile $uploadedFile
     * @param UserInterface $user
     * @return File
     */
    public function createFromFile(UploadedFile $uploadedFile, UserInterface $user): File
    {
        $file = new File()
            ->setUser($user)
            ->setFileKey(Uuid::v4())
            ->setOriginalName($uploadedFile->getClientOriginalName())
            ->setMimeType(MimeType::tryFrom($uploadedFile->getMimeType()))
            ->setSize($uploadedFile->getSize())
            ->setType(FileType::determineFileType(MimeType::tryFrom($uploadedFile->getMimeType())))
            ->setStatus(FileStatus::PENDING)
            ->setVersion(1);

        $violations = $this->validator->validate($file);
        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        return $file;
    }
}
