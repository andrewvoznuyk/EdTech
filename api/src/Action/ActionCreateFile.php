<?php

namespace App\Action;

use App\Entity\File;
use App\Enum\FileStatus;
use App\Enum\FileType;
use App\Enum\MimeType;
use App\Service\Interface\IBValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

readonly class ActionCreateFile
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param IBValidatorInterface $validator
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IBValidatorInterface $validator,
    )
    {}

    /**
     * @param UploadedFile $uploadedFile
     * @param UserInterface $user
     * @return File
     */
    public function __invoke(UploadedFile $uploadedFile, UserInterface $user): File
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

        $this->validator->validate($file);
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        return $file;
    }
}
