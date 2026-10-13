<?php

namespace App\Service\Security\Encryption;

use Random\RandomException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class SecretCipher implements SecretCipherInterface
{
    private const string CIPHER_ALGO = 'aes-256-gcm';
    private const int IV_LENGTH = 12;
    private const int TAG_LENGTH = 16;

    /**
     * @var string
     */
    private string $key;

    /**
     * @param string $appSecret
     */
    public function __construct(
        #[Autowire('%kernel.secret%')] string $appSecret
    ) {
        $this->key = hash('sha256', $appSecret, true);
    }

    /**
     * @param string $plaintext
     * @return string
     * @throws RandomException
     */
    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_ALGO,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * @param string $ciphertext
     * @return string
     * @throws RuntimeException
     */
    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, true);
        $minPrefixLength = self::IV_LENGTH + self::TAG_LENGTH;

        if (false === $decoded || strlen($decoded) <= $minPrefixLength) {
            throw new RuntimeException('Invalid encrypted payload format.');
        }

        $iv = substr($decoded, 0, self::IV_LENGTH);
        $tag = substr($decoded, self::IV_LENGTH, self::TAG_LENGTH);
        $encryptedPayload = substr($decoded, $minPrefixLength);

        $decrypted = openssl_decrypt(
            $encryptedPayload,
            self::CIPHER_ALGO,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if (false === $decrypted) {
            throw new RuntimeException('Failed to decrypt payload: authentication tag mismatch or invalid key.');
        }

        return $decrypted;
    }
}
