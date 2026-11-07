<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Hashing;

use MacropaySolutions\Kernel\Contracts\Hashing\Hasher;
use MacropaySolutions\Kernel\Hashing\HashManager;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;

/**
 * bcrypt, argon2i and argon2id hashing: make, check, rehash decisions and algorithm verification.
 */
class HashingTest extends ApplicationTestCase
{
    public function test_the_default_hasher_is_bcrypt(): void
    {
        $hash = $this->app->make(Hasher::class)->make('secret');

        $this->assertSame('bcrypt', $this->app->make(Hasher::class)->info($hash)['algoName']);
        $this->assertTrue($this->app->make(Hasher::class)->check('secret', $hash));
        $this->assertFalse($this->app->make(Hasher::class)->check('wrong', $hash));
        $this->assertFalse($this->app->make(Hasher::class)->check('secret', ''));
        $this->assertTrue($this->app->make(Hasher::class)->isHashed($hash));
        $this->assertFalse($this->app->make(Hasher::class)->isHashed('secret'));
    }

    public function test_bcrypt_asks_for_a_rehash_when_the_cost_goes_up(): void
    {
        $bcrypt = $this->manager()->driver('bcrypt');
        $hash = $bcrypt->make('secret', ['rounds' => 4]);

        $this->assertFalse($bcrypt->needsRehash($hash, ['rounds' => 4]));
        $this->assertTrue($bcrypt->needsRehash($hash, ['rounds' => 5]));
    }

    public function test_bcrypt_with_verify_refuses_hashes_of_another_algorithm(): void
    {
        $argon = $this->manager()->driver('argon')->make('secret');
        $this->app->make('config')->set('hashing.bcrypt', ['verify' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not use the Bcrypt algorithm');

        $this->manager()->driver('bcrypt')->check('secret', $argon);
    }

    public function test_argon_drivers(): void
    {
        if (!\defined('PASSWORD_ARGON2I') || !\defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('PHP is built without Argon2.');
        }

        $argon = $this->manager()->driver('argon');
        $argon2id = $this->manager()->driver('argon2id');

        $this->assertTrue($argon->check('secret', $argon->make('secret')));
        $this->assertSame('argon2i', $argon->info($argon->make('secret'))['algoName']);
        $this->assertTrue($argon2id->check('secret', $argon2id->make('secret')));
        $this->assertSame('argon2id', $argon2id->info($argon2id->make('secret'))['algoName']);
        $this->assertTrue(
            $argon->needsRehash($argon->make('secret', ['memory' => 1024]), ['memory' => 2048])
        );
    }

    private function manager(): HashManager
    {
        return $this->app->make(HashManager::class);
    }
}
