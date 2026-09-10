<?php

namespace MacropaySolutions\Kernel\Contracts\Encryption;

interface StringEncrypter
{
    /**
     * Encrypt a string without serialization.
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Encryption\EncryptException
     */
    public function encryptString(string $value): string;

    /**
     * Decrypt the given string without unserialization.
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Encryption\DecryptException
     */
    public function decryptString(string $payload): string;
}
