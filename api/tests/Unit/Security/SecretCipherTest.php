<?php

namespace App\Tests\Unit\Security;

use App\Service\Security\Encryption\SecretCipher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SecretCipherTest extends TestCase
{
    /**
     * @var SecretCipher
     */
    private SecretCipher $cipher;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->cipher = new SecretCipher('test-secret-key-1234567890123456');
    }

    /**
     * @return void
     */
    public function testEncryptAndDecrypt(): void
    {
        $plaintext = 'SecretOtpKey_12345';
        $encrypted = $this->cipher->encrypt($plaintext);

        $this->assertNotEmpty($encrypted);
        $this->assertNotSame($plaintext, $encrypted);

        $decrypted = $this->cipher->decrypt($encrypted);
        $this->assertSame($plaintext, $decrypted);
    }

    /**
     * @return void
     */
    public function testDecryptThrowsOnInvalidFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid encrypted payload format.');

        $this->cipher->decrypt('short_string');
    }

    /**
     * @return void
     */
    public function testDecryptThrowsOnTamperedPayload(): void
    {
        $plaintext = 'SecretOtpKey';
        $encrypted = $this->cipher->encrypt($plaintext);

        $decoded = base64_decode($encrypted, true);
        // Tamper with the last byte
        $tampered = substr($decoded, 0, -1) . chr(ord(substr($decoded, -1)) ^ 0xFF);
        $tamperedBase64 = base64_encode($tampered);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to decrypt payload: authentication tag mismatch or invalid key.');

        $this->cipher->decrypt($tamperedBase64);
    }
}
