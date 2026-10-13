<?php

namespace App\Service\File;

use App\Entity\File;
use Aws\Result;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface StorageServiceInterface
{
    /**
     * @param UploadedFile $uploadedFile
     * @param File $file
     * @return Result
     */
    public function upload(UploadedFile $uploadedFile, File $file): Result;

    /**
     * @param File $file
     * @param int $expires
     * @return string
     */
    public function generatePresignedUploadUrl(File $file, int $expires = 300): string;

    /**
     * @param File $file
     * @param int $expires
     * @return string
     */
    public function generatePresignedDownloadUrl(File $file, int $expires = 300): string;

    /**
     * @param File[] $files
     * @param int $expires
     * @return array<string, string>
     */
    public function generatePresignedDownloadUrls(array $files, int $expires = 300): array;

    /**
     * @param File $file
     * @return Result
     */
    public function delete(File $file): Result;
}
