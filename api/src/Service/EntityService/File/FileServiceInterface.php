<?php

namespace App\Service\EntityService\File;

use App\Entity\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\User\UserInterface;

interface FileServiceInterface
{
    /**
     * @param UploadedFile $uploadedFile
     * @param UserInterface $user
     * @return File
     */
    public function createFromFile(UploadedFile $uploadedFile, UserInterface $user): File;
}
