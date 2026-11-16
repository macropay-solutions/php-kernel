<?php

namespace MacropaySolutions\Kernel\Tests\Hashing;

use MacropaySolutions\Kernel\Hashing\BcryptHasher;
use PHPUnit\Framework\TestCase;

/**
 * check() ignored its 'verify' option: only the hasher's configuration decided whether the algorithm was verified.
 */
class HashVerifyOptionTest extends TestCase
{
    public function testTheVerifyOptionOfTheCallIsApplied(): void
    {
        $argon = \password_hash('secret', \PASSWORD_ARGON2I);

        $this->assertTrue((new BcryptHasher(['verify' => true]))->check('secret', $argon, ['verify' => false]));

        $this->expectException(\RuntimeException::class);

        (new BcryptHasher(['verify' => false]))->check('secret', $argon, ['verify' => true]);
    }
}
