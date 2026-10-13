<?php

namespace App\Tests\Unit\Enum;

use App\Enum\AssetType;
use App\Enum\EmailType;
use App\Enum\FileStatus;
use App\Enum\FileType;
use App\Enum\MimeType;
use App\Enum\TwoFactorMethodEnum;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    /**
     * @return void
     */
    public function testFileTypeDetermine(): void
    {
        $this->assertSame(FileType::IMAGE, FileType::determineFileType(MimeType::JPEG));
        $this->assertSame(FileType::IMAGE, FileType::determineFileType(MimeType::PNG));
        $this->assertSame(FileType::IMAGE, FileType::determineFileType(MimeType::WEBP));
        $this->assertSame(FileType::IMAGE, FileType::determineFileType(MimeType::SVG));
        $this->assertSame(FileType::DOCUMENT, FileType::determineFileType(MimeType::PDF));
        $this->assertSame(FileType::OTHER, FileType::determineFileType(null));
    }

    /**
     * @return void
     */
    public function testEnumValuesTrait(): void
    {
        $assetValues = AssetType::values();
        $this->assertContains('widget', $assetValues);

        $emailValues = EmailType::values();
        $this->assertContains('verification', $emailValues);
        $this->assertContains('password_reset', $emailValues);
        $this->assertContains('resend_2fa', $emailValues);
        $this->assertContains('restore_account', $emailValues);

        $twoFactorValues = TwoFactorMethodEnum::values();
        $this->assertContains('email', $twoFactorValues);
        $this->assertContains('totp', $twoFactorValues);
        $this->assertContains('backup_code', $twoFactorValues);

        $statusValues = FileStatus::values();
        $this->assertContains('pending', $statusValues);
        $this->assertContains('uploaded', $statusValues);
    }
}
