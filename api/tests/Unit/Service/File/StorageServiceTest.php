<?php

namespace App\Tests\Unit\Service\File;

use App\Entity\File;
use App\Service\File\StorageService;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StorageServiceTest extends TestCase
{
    /**
     * @var S3Client
     */
    private S3Client $s3Client;

    /**
     * @var StorageService
     */
    private StorageService $storageService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->s3Client = new class extends S3Client {
            public ?array $lastPutArgs = null;
            public ?array $lastDeleteArgs = null;
            public ?string $lastCommandName = null;
            public ?array $lastCommandArgs = null;
            public mixed $lastExpires = null;
            public ?Result $putResult = null;
            public ?Result $deleteResult = null;
            public ?CommandInterface $commandToReturn = null;
            public ?RequestInterface $requestToReturn = null;

            public function __construct()
            {
            }

            public function putObject(array $args = []): Result
            {
                $this->lastPutArgs = $args;
                return $this->putResult ?? new Result();
            }

            public function deleteObject(array $args = []): Result
            {
                $this->lastDeleteArgs = $args;
                return $this->deleteResult ?? new Result();
            }

            public function getCommand($name, array $args = []): CommandInterface
            {
                $this->lastCommandName = $name;
                $this->lastCommandArgs = $args;
                return $this->commandToReturn;
            }

            public function createPresignedRequest(CommandInterface $command, $expires, array $options = []): RequestInterface
            {
                $this->lastExpires = $expires;
                return $this->requestToReturn;
            }
        };

        $this->storageService = new StorageService($this->s3Client, 'test-bucket');
    }

    /**
     * @return void
     */
    public function testUpload(): void
    {
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getPathname')->willReturn('/tmp/php123');
        $uploadedFile->method('getMimeType')->willReturn('image/png');

        $file = new File();
        $file->setFileKey('file-uuid-key');

        $expectedResult = new Result(['ObjectURL' => 'https://s3/test-bucket/file-uuid-key']);
        $this->s3Client->putResult = $expectedResult;

        $result = $this->storageService->upload($uploadedFile, $file);

        $this->assertSame($expectedResult, $result);
        $this->assertSame('test-bucket', $this->s3Client->lastPutArgs['Bucket']);
        $this->assertSame('file-uuid-key', $this->s3Client->lastPutArgs['Key']);
        $this->assertSame('/tmp/php123', $this->s3Client->lastPutArgs['SourceFile']);
        $this->assertSame('image/png', $this->s3Client->lastPutArgs['ContentType']);
    }

    /**
     * @return void
     */
    public function testGeneratePresignedUploadUrl(): void
    {
        $file = new File();
        $file->setFileKey('file-key-upload');

        $command = $this->createMock(CommandInterface::class);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('https://s3/presigned-upload-url');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $this->s3Client->commandToReturn = $command;
        $this->s3Client->requestToReturn = $request;

        $url = $this->storageService->generatePresignedUploadUrl($file, 300);

        $this->assertSame('https://s3/presigned-upload-url', $url);
        $this->assertSame('PutObject', $this->s3Client->lastCommandName);
        $this->assertSame('+300 seconds', $this->s3Client->lastExpires);
    }

    /**
     * @return void
     */
    public function testGeneratePresignedDownloadUrl(): void
    {
        $file = new File();
        $file->setFileKey('file-key-download');

        $command = $this->createMock(CommandInterface::class);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('https://s3/presigned-download-url');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $this->s3Client->commandToReturn = $command;
        $this->s3Client->requestToReturn = $request;

        $url = $this->storageService->generatePresignedDownloadUrl($file, 600);

        $this->assertSame('https://s3/presigned-download-url', $url);
        $this->assertSame('GetObject', $this->s3Client->lastCommandName);
        $this->assertSame('+600 seconds', $this->s3Client->lastExpires);
    }

    /**
     * @return void
     */
    public function testGeneratePresignedDownloadUrlsBatch(): void
    {
        $file1 = new File();
        $file1->setFileKey('key1');

        $file2 = new File();
        $file2->setFileKey('key2');

        $command = $this->createMock(CommandInterface::class);
        $uri = $this->createMock(UriInterface::class);
        $uri->method('__toString')->willReturn('https://s3/download-url');

        $request = $this->createMock(RequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $this->s3Client->commandToReturn = $command;
        $this->s3Client->requestToReturn = $request;

        $urls = $this->storageService->generatePresignedDownloadUrls([$file1, $file2], 300);

        $this->assertCount(2, $urls);
        $this->assertSame('https://s3/download-url', $urls['key1']);
        $this->assertSame('https://s3/download-url', $urls['key2']);
    }

    /**
     * @return void
     */
    public function testDelete(): void
    {
        $file = new File();
        $file->setFileKey('file-to-delete');

        $expectedResult = new Result();
        $this->s3Client->deleteResult = $expectedResult;

        $result = $this->storageService->delete($file);

        $this->assertSame($expectedResult, $result);
        $this->assertSame('test-bucket', $this->s3Client->lastDeleteArgs['Bucket']);
        $this->assertSame('file-to-delete', $this->s3Client->lastDeleteArgs['Key']);
    }
}
