<?php

namespace App\Tests\Unit\Service\File;

use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\File;
use App\Entity\User;
use App\Enum\FileStatus;
use App\Enum\FileType;
use App\Enum\MimeType;
use App\Service\EntityService\File\FileService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FileServiceTest extends TestCase
{
    /**
     * @var EntityManagerInterface&MockObject
     */
    private EntityManagerInterface&MockObject $entityManager;

    /**
     * @var ValidatorInterface&MockObject
     */
    private ValidatorInterface&MockObject $validator;

    /**
     * @var FileService
     */
    private FileService $fileService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->fileService = new FileService($this->entityManager, $this->validator);
    }

    /**
     * @return void
     */
    public function testCreateFromFileSuccess(): void
    {
        $user = new User();
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getClientOriginalName')->willReturn('photo.jpg');
        $uploadedFile->method('getMimeType')->willReturn('image/jpeg');
        $uploadedFile->method('getSize')->willReturn(102400);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(File::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $file = $this->fileService->createFromFile($uploadedFile, $user);

        $this->assertSame($user, $file->getUser());
        $this->assertSame('photo.jpg', $file->getOriginalName());
        $this->assertSame(MimeType::JPEG, $file->getMimeType());
        $this->assertSame(FileType::IMAGE, $file->getType());
        $this->assertSame(FileStatus::PENDING, $file->getStatus());
        $this->assertSame(102400, $file->getSize());
        $this->assertSame(1, $file->getVersion());
        $this->assertNotNull($file->getFileKey());
    }

    /**
     * @return void
     */
    public function testCreateFromFileThrowsValidationException(): void
    {
        $user = new User();
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getClientOriginalName')->willReturn('photo.jpg');
        $uploadedFile->method('getMimeType')->willReturn('image/jpeg');
        $uploadedFile->method('getSize')->willReturn(10000000);

        $violation = $this->createMock(ConstraintViolationInterface::class);
        $violations = new ConstraintViolationList([$violation]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $this->expectException(ValidationException::class);

        $this->fileService->createFromFile($uploadedFile, $user);
    }
}
