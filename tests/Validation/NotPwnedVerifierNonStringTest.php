<?php

namespace MacropaySolutions\Kernel\Tests\Validation;

use MacropaySolutions\Kernel\Validation\NotPwnedVerifier;
use PHPUnit\Framework\TestCase;

/**
 * An array or object given as the password was cast to string, which only warned "Array to string conversion".
 */
class NotPwnedVerifierNonStringTest extends TestCase
{
    public function testNonStringValuesAreNotVerified(): void
    {
        $verifier = (new \ReflectionClass(NotPwnedVerifier::class))->newInstanceWithoutConstructor();
        $warnings = [];
        \set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $this->assertFalse($verifier->verify(['value' => ['123'], 'threshold' => 0]));
            $this->assertFalse($verifier->verify(['value' => new \stdClass(), 'threshold' => 0]));
            $this->assertFalse($verifier->verify(['value' => '', 'threshold' => 0]));
        } finally {
            \restore_error_handler();
        }

        $this->assertSame([], $warnings);
    }
}
