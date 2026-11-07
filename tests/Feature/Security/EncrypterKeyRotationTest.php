<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Security;

use MacropaySolutions\Kernel\Contracts\Encryption\DecryptException;
use MacropaySolutions\Kernel\Encryption\Encrypter;
use MacropaySolutions\Kernel\Encryption\EncryptionServiceProvider;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;

/**
 * Key and cipher rotation: payloads made with a previous key and cipher still decrypt, every candidate key is
 * checked with hash_equals, and a payload that does not belong to any configured key/cipher pair is refused.
 */
class EncrypterKeyRotationTest extends ApplicationTestCase
{
    public function test_a_payload_from_a_previous_key_and_cipher_decrypts_after_rotation(): void
    {
        $oldKey = Encrypter::generateKey('aes-256-cbc');
        $payload = (new Encrypter($oldKey, 'aes-256-cbc'))->encryptString('card-token');

        $rotated = new Encrypter(Encrypter::generateKey('aes-256-gcm'), 'aes-256-gcm', [$oldKey => 'aes-256-cbc']);

        $this->assertSame('card-token', $rotated->decryptString($payload));
        $this->assertSame('card-token', $rotated->decryptString($rotated->encryptString('card-token')));
    }

    public function test_the_service_provider_reads_the_previous_keys_cipher_map(): void
    {
        $oldKey = Encrypter::generateKey('aes-256-cbc');
        $payload = (new Encrypter($oldKey, 'aes-256-cbc'))->encryptString('secret');
        $config = $this->app->make('config');
        $config->set('app.cipher', 'aes-256-gcm');
        $config->set('app.key', 'base64:' . \base64_encode(Encrypter::generateKey('aes-256-gcm')));
        $config->set('app.previous_keys_cipher_map', ['base64:' . \base64_encode($oldKey) => 'aes-256-cbc']);

        $encrypter = EncryptionServiceProvider::getEncrypter($this->app);

        $this->assertSame('secret', $encrypter->decryptString($payload));
        $this->assertCount(2, $encrypter->getKey(true));
    }

    public function test_a_payload_from_an_unknown_key_is_refused(): void
    {
        $payload = (new Encrypter(Encrypter::generateKey('aes-256-cbc'), 'aes-256-cbc'))->encryptString('secret');

        $this->expectException(DecryptException::class);

        (new Encrypter(Encrypter::generateKey('aes-256-cbc'), 'aes-256-cbc'))->decryptString($payload);
    }

    public function test_a_tampered_mac_is_refused(): void
    {
        $encrypter = new Encrypter(Encrypter::generateKey('aes-256-cbc'), 'aes-256-cbc');
        $payload = \json_decode(\base64_decode($encrypter->encryptString('secret')), true);
        $payload['mac'] = \str_repeat('0', 64);

        $this->expectException(DecryptException::class);

        $encrypter->decryptString(\base64_encode(\json_encode($payload)));
    }

    public function test_a_cbc_payload_carrying_a_tag_is_refused(): void
    {
        $encrypter = new Encrypter(Encrypter::generateKey('aes-256-cbc'), 'aes-256-cbc');
        $payload = \json_decode(\base64_decode($encrypter->encryptString('secret')), true);
        $payload['tag'] = \base64_encode(\str_repeat('a', 16));

        $this->expectException(DecryptException::class);

        $encrypter->decryptString(\base64_encode(\json_encode($payload)));
    }

    public function test_an_invalid_previous_key_is_rejected_at_construction(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('incorrect key length of previous key 0');

        new Encrypter(Encrypter::generateKey('aes-256-cbc'), 'aes-256-cbc', ['short' => 'aes-256-cbc']);
    }
}
