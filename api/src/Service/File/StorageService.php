<?php

namespace App\Service\File;

use App\Entity\File;
use Aws\Result;
use Aws\S3\S3Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @implements StorageServiceInterface
 */
readonly class StorageService implements StorageServiceInterface
{
    /**
     * @param S3Client $s3
     * @param string $bucket
     */
    public function __construct(
        private S3Client $s3,
        #[Autowire('%env(AWS_BUCKET)%')]
        private string $bucket,
    ) {
    }

    /**
     * @param UploadedFile $uploadedFile
     * @param File $file
     * @return Result
     */
    public function upload(UploadedFile $uploadedFile, File $file): Result
    {
        return $this->s3->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $file->getFileKey(),
            'SourceFile'  => $uploadedFile->getPathname(),
            'ContentType' => $uploadedFile->getMimeType(),
            'ACL'         => 'private',
        ]);
    }

    /**
     * @param File $file
     * @param int $expires
     * @return string
     */
    public function generatePresignedUploadUrl(File $file, int $expires = 300): string
    {
        $command = $this->s3->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key'    => $file->getFileKey(),
        ]);

        return (string) $this->s3->createPresignedRequest($command, "+{$expires} seconds")->getUri();
    }

    /**
     * @param File $file
     * @param int $expires
     * @return string
     */
    public function generatePresignedDownloadUrl(File $file, int $expires = 300): string
    {
        $command = $this->s3->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key'    => $file->getFileKey(),
        ]);

        return (string) $this->s3->createPresignedRequest($command, "+{$expires} seconds")->getUri();
    }

    /**
     * @param File[] $files
     * @param int $expires
     * @return array<string, string>
     */
    public function generatePresignedDownloadUrls(array $files, int $expires = 300): array
    {
        $urls = [];
        foreach ($files as $file) {
            $urls[$file->getFileKey()] = $this->generatePresignedDownloadUrl($file, $expires);
        }

        return $urls;
    }

    /**
     * @param File $file
     * @return Result
     */
    public function delete(File $file): Result
    {
        return $this->s3->deleteObject([
            'Bucket' => $this->bucket,
            'Key'    => $file->getFileKey(),
        ]);
    }
}
