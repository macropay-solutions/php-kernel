<?php

namespace MacropaySolutions\Kernel\Encryption;

use MacropaySolutions\Kernel\Contracts\Encryption\DecryptException;
use MacropaySolutions\Kernel\Contracts\Encryption\Encrypter as EncrypterContract;
use MacropaySolutions\Kernel\Contracts\Encryption\EncryptException;
use MacropaySolutions\Kernel\Contracts\Encryption\StringEncrypter;
use Random\RandomException;

class Encrypter implements EncrypterContract, StringEncrypter
{
    /**
     * The encryption key.
     */
    protected string $key;

    /**
     * The previous / legacy encryption keys to ciphers map
     */
    protected array $previousKeysCiphersMap = [];

    /**
     * The algorithm used for encryption.
     */
    protected string $cipher;

    /**
     * The supported cipher algorithms and their properties.
     *
     * @var array
     */
    private static array $supportedCiphers = [
        'aes-128-cbc' => ['size' => 16, 'aead' => false],
        'aes-256-cbc' => ['size' => 32, 'aead' => false],
        'aes-128-gcm' => ['size' => 16, 'aead' => true],
        'aes-256-gcm' => ['size' => 32, 'aead' => true],
    ];

    /**
     * Create a new encrypter instance.
     *
     * @throws \RuntimeException
     */
    public function __construct(string $key, string $cipher = 'aes-128-cbc', array $previousKeysCiphersMap = [])
    {
        $cipher = \strtolower($cipher);

        if (!static::supported($key, $cipher)) {
            $ciphers = implode(', ', array_keys(self::$supportedCiphers));

            throw new \RuntimeException(
                "Unsupported cipher or incorrect key length. Supported ciphers are: {$ciphers}."
            );
        }

        $this->key = $key;
        $this->cipher = $cipher;

        $index = 0;

        foreach ($previousKeysCiphersMap as $previousKey => $previousCipher) {
            $previousKey = (string)$previousKey;

            if (!static::supported($previousKey, \strtolower($previousCipher ?? $this->cipher))) {
                throw new \RuntimeException('Unsupported cipher or incorrect key length of previous key ' .
                    $index . ' Supported ciphers are: ' . \implode(', ', \array_keys(self::$supportedCiphers)));
            }

            $index++;
        }

        $this->previousKeysCiphersMap = $previousKeysCiphersMap;
    }

    /**
     * Determine if the given key and cipher combination is valid.
     */
    public static function supported(string $key, string $cipher): bool
    {
        $cipher = \strtolower($cipher);

        return isset(self::$supportedCiphers[$cipher])
            && \mb_strlen($key, '8bit') === self::$supportedCiphers[$cipher]['size'];
    }

    /**
     * Create a new encryption key for the given cipher.
     * @throws RandomException|\RuntimeException
     */
    public static function generateKey(string $cipher): string
    {
        $cipher = strtolower($cipher);

        if (!isset(self::$supportedCiphers[$cipher])) {
            throw new \RuntimeException('Unsupported cipher: ' . $cipher);
        }

        return \random_bytes(self::$supportedCiphers[$cipher]['size']);
    }

    /**
     * Encrypt the given value.
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Encryption\EncryptException|\Random\RandomException
     */
    public function encrypt(mixed $value, bool $serialize = true): string
    {
        $ivLength = \openssl_cipher_iv_length(strtolower($this->cipher)) ?: 16;
        $iv = \random_bytes($ivLength);

        $value = \openssl_encrypt(
            $serialize ? serialize($value) : $value,
            strtolower($this->cipher),
            $this->key,
            0,
            $iv,
            $tag
        );

        if ($value === false) {
            throw new EncryptException('Could not encrypt the data.');
        }

        $iv = base64_encode($iv);
        $tag = base64_encode($tag ?? '');

        $mac = self::$supportedCiphers[strtolower($this->cipher)]['aead']
            ? '' // For AEAD-algorithms, the tag / MAC is returned by openssl_encrypt...
            : $this->hash($iv, $value);

        $json = json_encode(compact('iv', 'value', 'mac', 'tag'), JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new EncryptException('Could not encrypt the data.');
        }

        return base64_encode($json);
    }

    /**
     * Encrypt a string without serialization.
     *
     * @throws \MacropaySolutions\Kernel\Contracts\Encryption\EncryptException|\Random\RandomException
     */
    public function encryptString(string $value): string
    {
        return $this->encrypt($value, false);
    }

    /**
     * Decrypt the given value.
     *
     * @throws DecryptException
     */
    public function decrypt(string $payload, bool $unserialize = true): mixed
    {
        $payload = $this->getJsonPayload($payload);

        $iv = \base64_decode($payload['iv'], true);

        if ($iv === false) {
            throw new DecryptException('The payload IV is invalid Base64.');
        }

        $tag = null;

        if ('' !== ($payload['tag'] ?? '')) {
            $tag = \base64_decode($payload['tag'], true);

            if ($tag === false) {
                throw new DecryptException('The payload MAC tag is invalid Base64.');
            }
        }

        $candidates = [
            [$this->key, $this->cipher],
        ];

        foreach ($this->previousKeysCiphersMap as $key => $cipher) {
            $candidates[] = [$key, $cipher ?? $this->cipher];
        }

        $successfulPayload = null;

        foreach ($candidates as [$candidateKey, $candidateCipher]) {
            $candidateCipher = \strtolower($candidateCipher);
            $isAead = self::$supportedCiphers[$candidateCipher]['aead'];

            $validTag = $this->isTagValidForCipher($tag, $candidateCipher);

            $expectedMac = $this->hash($payload['iv'], $payload['value'], $candidateKey);
            $validMac = \hash_equals($expectedMac, $payload['mac']);

            if ($validTag && ($isAead || $validMac)) {
                $decrypted = \openssl_decrypt(
                    $payload['value'],
                    $candidateCipher,
                    $candidateKey,
                    0,
                    $iv,
                    $tag ?? ''
                );

                if ($decrypted !== false && $successfulPayload === null) {
                    $successfulPayload = $decrypted;
                }
            }
        }

        if ($successfulPayload !== null) {
            if (!$unserialize) {
                return $successfulPayload;
            }

            $result = \unserialize($successfulPayload, ['allowed_classes' => false]);

            if ($result === false && $successfulPayload !== \serialize(false)) {
                throw new DecryptException('The decrypted data is invalid.');
            }

            return $result;
        }

        throw new DecryptException('Could not decrypt the data.');
    }

    /**
     * Decrypt the given string without unserialization.
     *
     * @throws DecryptException
     */
    public function decryptString(string $payload): string
    {
        return $this->decrypt($payload, false);
    }

    /**
     * Create a MAC for the given value.
     */
    protected function hash(string $iv, mixed $value, ?string $key = null): string
    {
        return hash_hmac('sha256', $iv . $value, $key ?? $this->key);
    }

    /**
     * Get the JSON array from the given payload.
     *
     * @throws DecryptException
     */
    protected function getJsonPayload(string $payload): array
    {
        $decoded = \base64_decode($payload, true);

        if ($decoded === false) {
            throw new DecryptException('The payload is invalid Base64.');
        }

        $payload = \json_decode($decoded, true);

        if (!$this->validPayload($payload)) {
            throw new DecryptException('The payload is invalid.');
        }

        return $payload;
    }

    /**
     * Verify that the encryption payload is valid.
     */
    protected function validPayload(mixed $payload): bool
    {
        if (!\is_array($payload)) {
            return false;
        }

        foreach (['iv', 'value', 'mac'] as $item) {
            if (!isset($payload[$item]) || !\is_string($payload[$item])) {
                return false;
            }
        }

        if (\base64_decode($payload['value'], true) === false) {
            return false;
        }

        if (isset($payload['tag'])) {
            if (!\is_string($payload['tag']) || \base64_decode($payload['tag'], true) === false) {
                return false;
            }
        }

        $iv = \base64_decode($payload['iv'], true);

        if ($iv === false) {
            return false;
        }

        $length = \strlen($iv);
        $candidates = [$this->cipher];

        foreach ($this->previousKeysCiphersMap as $oldCipher) {
            $candidates[] = $oldCipher ?? $this->cipher;
        }

        foreach ($candidates as $candidateCipher) {
            if ($length === \openssl_cipher_iv_length(\strtolower($candidateCipher))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure the given tag is structurally valid for the candidate cipher.
     */
    protected function isTagValidForCipher(null|bool|string $tag, string $cipher): bool
    {
        if (self::$supportedCiphers[\strtolower($cipher)]['aead']) {
            return \is_string($tag) && \strlen($tag) === 16;
        }

        return !\is_string($tag);
    }

    /**
     * Get the encryption key that the encrypter is currently using or all
     */
    public function getKey(?bool $includingPrevious = false): string|array
    {
        if ($includingPrevious) {
            return [$this->key, ...\array_keys($this->previousKeysCiphersMap)];
        }

        return $this->key;
    }
}
