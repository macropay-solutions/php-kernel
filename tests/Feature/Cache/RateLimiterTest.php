<?php

namespace MacropaySolutions\Kernel\Tests\Feature\Cache;

use MacropaySolutions\Kernel\Cache\RateLimiter;
use MacropaySolutions\Kernel\Tests\ApplicationTestCase;

/**
 * The application-level rate limiter on the configured cache: attempts, decay, remaining and clearing.
 */
class RateLimiterTest extends ApplicationTestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->limiter = $this->app->make(RateLimiter::class);
    }

    public function test_attempt_runs_the_callback_until_the_attempts_are_used_up(): void
    {
        $runs = 0;
        $callback = static function () use (&$runs): string {
            $runs++;

            return 'sent';
        };

        $this->assertSame('sent', $this->limiter->attempt('send:7', 2, $callback));
        $this->assertSame('sent', $this->limiter->attempt('send:7', 2, $callback));
        $this->assertFalse($this->limiter->attempt('send:7', 2, $callback));
        $this->assertSame(2, $runs);
    }

    public function test_hits_remaining_and_available_in(): void
    {
        $this->limiter->hit('login:ana', 60);
        $this->limiter->hit('login:ana', 60);

        $this->assertSame(2, $this->limiter->attempts('login:ana'));
        $this->assertSame(1, $this->limiter->remaining('login:ana', 3));
        $this->assertFalse($this->limiter->tooManyAttempts('login:ana', 3));

        $this->limiter->hit('login:ana', 60);

        $this->assertTrue($this->limiter->tooManyAttempts('login:ana', 3));
        $this->assertSame(0, $this->limiter->remaining('login:ana', 3));
        $this->assertGreaterThan(0, $this->limiter->availableIn('login:ana'));
        $this->assertLessThanOrEqual(60, $this->limiter->availableIn('login:ana'));
    }

    public function test_attempts_reset_after_the_decay_window(): void
    {
        $this->limiter->hit('report', 10);
        $this->limiter->hit('report', 10);

        $this->assertTrue($this->limiter->tooManyAttempts('report', 2));

        $this->travelSeconds(11);

        $this->assertFalse($this->limiter->tooManyAttempts('report', 2));
        $this->assertSame(2, $this->limiter->remaining('report', 2));
    }

    public function test_increment_by_an_amount_and_clear(): void
    {
        $this->limiter->increment('upload', 60, 5);

        $this->assertSame(5, $this->limiter->attempts('upload'));
        $this->assertTrue($this->limiter->tooManyAttempts('upload', 5));

        $this->limiter->clear('upload');

        $this->assertSame(0, $this->limiter->attempts('upload'));
        $this->assertFalse($this->limiter->tooManyAttempts('upload', 5));
    }

    public function test_keys_are_independent(): void
    {
        $this->limiter->hit('send:1');

        $this->assertSame(1, $this->limiter->attempts('send:1'));
        $this->assertSame(0, $this->limiter->attempts('send:2'));
    }
}
