<?php

namespace App\Service\Security\Encryption;

interface SecretCipherInterface
{
    /**
     * @param string $plaintext
     * @return string
     */
    public function encrypt(string $plaintext): string;

    /**
     * @param string $ciphertext
     * @return string
     */
    public function decrypt(string $ciphertext): string;
}
