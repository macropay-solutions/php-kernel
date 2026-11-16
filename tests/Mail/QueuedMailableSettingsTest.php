<?php

namespace MacropaySolutions\Kernel\Tests\Mail;

use MacropaySolutions\Kernel\Mail\Mailable;
use MacropaySolutions\Kernel\Mail\SendQueuedMailable;
use PHPUnit\Framework\TestCase;

/**
 * The queued mail job took tries() / backoff() from the mailable's methods, then overwrote tries with the missing
 * $tries property (null).
 */
class QueuedMailableSettingsTest extends TestCase
{
    public function testSettingsFromMethodsAreKept(): void
    {
        $callable = (new SendQueuedMailable(new QueuedMailableSettingsTestMail()))->toStorableCallable();

        $this->assertSame(4, $callable->tries);
        $this->assertSame([10, 60], $callable->backoff);
        $this->assertSame(30, $callable->timeout);
    }
}

class QueuedMailableSettingsTestMail extends Mailable
{
    public $timeout = 30;

    public function tries(): int
    {
        return 4;
    }

    public function backoff(): array
    {
        return [10, 60];
    }
}
