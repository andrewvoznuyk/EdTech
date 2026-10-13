<?php

namespace App\Tests\Unit\Entity;

use App\Entity\File;
use App\Entity\User;
use App\Enum\FileStatus;
use App\Enum\FileType;
use App\Enum\MimeType;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{
    /**
     * @return void
     */
    public function testDefaultValues(): void
    {
        $file = new File();

        $this->assertNull($file->getId());
        $this->assertNull($file->getUser());
        $this->assertNull($file->getFileKey());
        $this->assertNull($file->getOriginalName());
        $this->assertSame(MimeType::JPEG, $file->getMimeType());
        $this->assertNull($file->getSize());
        $this->assertSame(FileStatus::PENDING, $file->getStatus());
        $this->assertSame([], $file->getMetadata());
        $this->assertSame(FileType::IMAGE, $file->getType());
        $this->assertSame(0, $file->getVersion());
    }

    /**
     * @return void
     */
    public function testSettersAndGetters(): void
    {
        $file = new File();
        $user = new User();

        $file->setUser($user);
        $file->setFileKey('custom-key-123');
        $file->setOriginalName('document.pdf');
        $file->setMimeType(MimeType::PDF);
        $file->setSize(1024);
        $file->setStatus(FileStatus::UPLOADED);
        $file->setMetadata(['width' => 100, 'height' => 200]);
        $file->setType(FileType::DOCUMENT);
        $file->setVersion(2);

        $this->assertSame($user, $file->getUser());
        $this->assertSame('custom-key-123', $file->getFileKey());
        $this->assertSame('document.pdf', $file->getOriginalName());
        $this->assertSame(MimeType::PDF, $file->getMimeType());
        $this->assertSame(1024, $file->getSize());
        $this->assertSame(FileStatus::UPLOADED, $file->getStatus());
        $this->assertSame(['width' => 100, 'height' => 200], $file->getMetadata());
        $this->assertSame(FileType::DOCUMENT, $file->getType());
        $this->assertSame(2, $file->getVersion());
    }
}
