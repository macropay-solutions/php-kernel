<?php

namespace MacropaySolutions\Kernel\Contracts\Encryption;

interface Encrypter
{
    /**
     * Encrypt the given value.
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Encryption\EncryptException
     */
    public function encrypt(mixed $value, bool $serialize = true): string;

    /**
     * Decrypt the given value.
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Encryption\DecryptException
     */
    public function decrypt(string $payload, bool $unserialize = true): mixed;

    /**
     * Get the encryption key that the encrypter is currently using.
     */
    public function getKey(?bool $includingPrevious = false): string|array;
}
