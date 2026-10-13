<?php

namespace App\Tests\Integration\Controller;

use App\Entity\File;
use App\Enum\FileStatus;
use App\Enum\FileType;
use App\Service\File\StorageServiceInterface;
use Aws\Result;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class FileControllerTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @var StorageServiceInterface
     */
    private StorageServiceInterface $storageServiceMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->storageServiceMock = $this->createMock(StorageServiceInterface::class);
        self::getContainer()->set(StorageServiceInterface::class, $this->storageServiceMock);
    }

    /**
     * @return void
     */
    public function testUploadUnauthorized(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempPath, 'dummy content');
        $uploadedFile = new UploadedFile($tempPath, 'test.pdf', 'application/pdf', null, true);

        $this->client->request('POST', '/api/file/upload', [], ['uploadedFile' => $uploadedFile]);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());

        @unlink($tempPath);
    }

    /**
     * @return void
     */
    public function testUploadSuccess(): void
    {
        $user = $this->createUser('uploader@example.com');
        $headers = $this->getAuthHeaders($user);

        $this->storageServiceMock
            ->expects($this->once())
            ->method('upload')
            ->willReturn(new Result(['ETag' => 'test-etag', 'Location' => 'https://s3/test']));

        $this->storageServiceMock
            ->expects($this->once())
            ->method('generatePresignedDownloadUrl')
            ->willReturn('https://s3.example.com/presigned-url');

        $tempPath = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempPath, 'dummy content for upload');
        $uploadedFile = new UploadedFile($tempPath, 'document.pdf', 'application/pdf', null, true);

        // In Symfony BrowserKit, remove 'CONTENT_TYPE' => 'application/json' for multipart file uploads
        unset($headers['CONTENT_TYPE']);

        $this->client->request('POST', '/api/file/upload', [], ['uploadedFile' => $uploadedFile], $headers);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('downloadUrl', $content);
        $this->assertSame('https://s3.example.com/presigned-url', $content['downloadUrl']);

        @unlink($tempPath);
    }

    /**
     * @return void
     */
    public function testDownloadUnauthorized(): void
    {
        $this->client->request('GET', '/api/file/download/some-file-key');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testDownloadSuccess(): void
    {
        $user = $this->createUser('downloader@example.com');
        $headers = $this->getAuthHeaders($user);

        $file = new File();
        $file->setFileKey('custom-key-123');
        $file->setOriginalName('sample.pdf');
        $file->setMimeType(\App\Enum\MimeType::PDF);
        $file->setType(FileType::DOCUMENT);
        $file->setSize(1024);
        $file->setStatus(FileStatus::UPLOADED);
        $file->setUser($user);
        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $this->storageServiceMock
            ->expects($this->once())
            ->method('generatePresignedDownloadUrl')
            ->with($this->callback(function (File $targetFile): bool {
                return $targetFile->getFileKey() === 'custom-key-123';
            }))
            ->willReturn('https://s3.example.com/download/custom-key-123');

        $this->client->request('GET', '/api/file/download/custom-key-123', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame(['https://s3.example.com/download/custom-key-123'], $content);
    }

    /**
     * @return void
     */
    public function testDownloadBatchEmptyFileKeys(): void
    {
        $user = $this->createUser('batch_downloader@example.com');
        $headers = $this->getAuthHeaders($user);

        $this->client->request('GET', '/api/file/download-batch', [], [], $headers);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $content);
        $this->assertArrayHasKey('fileKeys', $content['errors']);
    }

    /**
     * @return void
     */
    public function testDownloadBatchSuccess(): void
    {
        $user = $this->createUser('batch_downloader_success@example.com');
        $headers = $this->getAuthHeaders($user);

        $file1 = new File();
        $file1->setFileKey('batch-key-1');
        $file1->setOriginalName('f1.pdf');
        $file1->setMimeType(\App\Enum\MimeType::PDF);
        $file1->setType(FileType::DOCUMENT);
        $file1->setSize(500);
        $file1->setStatus(FileStatus::UPLOADED);
        $file1->setUser($user);
        $this->entityManager->persist($file1);

        $file2 = new File();
        $file2->setFileKey('batch-key-2');
        $file2->setOriginalName('f2.pdf');
        $file2->setMimeType(\App\Enum\MimeType::PDF);
        $file2->setType(FileType::DOCUMENT);
        $file2->setSize(800);
        $file2->setStatus(FileStatus::UPLOADED);
        $file2->setUser($user);
        $this->entityManager->persist($file2);

        $this->entityManager->flush();

        $this->storageServiceMock
            ->expects($this->once())
            ->method('generatePresignedDownloadUrls')
            ->willReturn([
                'batch-key-1' => 'https://s3/batch-key-1',
                'batch-key-2' => 'https://s3/batch-key-2',
            ]);

        $this->client->request('GET', '/api/file/download-batch?fileKeys[]=batch-key-1&fileKeys[]=batch-key-2', [], [], $headers);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('batch-key-1', $content);
        $this->assertArrayHasKey('batch-key-2', $content);
    }
}
